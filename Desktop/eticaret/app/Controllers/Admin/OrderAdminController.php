<?php
// app/Controllers/Admin/OrderAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\View;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OrderNotificationService;
use App\Services\OrderStatusService;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentService;
use App\Services\ShipmentService;
use DomainException;
use Throwable;

/**
 * Admin: sipariş listesi, detay, durum, kargo, havale/kapıda ödeme onayı, yazdır, CSV.
 * Her public metot en başta ilgili permission'ı require() eder (varsayılan: reddet).
 */
final class OrderAdminController extends AdminBaseController
{
    private const STATUSES = ['pending_payment', 'paid', 'preparing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    private const PAYMENT_METHODS = ['credit_card', 'bank_transfer', 'cod'];
    private const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];
    private const TABS = ['all', 'bank_pending', 'to_prepare', 'shipped'];

    /**
     * Genel durum formundan yalnızca bu hedeflere gidilir. paid → havale onayı/PayTR,
     * shipped → kargo formu, delivered → teslim butonu üzerinden yapılır; böylece
     * ödeme ve kargo kayıtları sipariş durumuyla tutarsız kalmaz.
     */
    private const FORM_TARGETS = ['preparing', 'cancelled', 'refunded'];

    private const CSV_MAX_PAGES = 50; // 50 x 100 = en çok 5000 satır
    private const CSV_FORMULA_CHARS = ['=', '+', '-', '@', "\t", "\r"];

    /** GET /yonetim/siparisler */
    public function index(Request $request): Response
    {
        $this->require('order.view');

        [$filters, $tab, $inputs] = $this->readFilters($request);
        $result = (new Order())->adminList($filters, $this->currentPage($request), $this->perPage());

        $bankPending = (new Payment())->pendingBankTransfers(1, 1);

        return $this->view('admin.orders.index', [
            'title'         => 'Siparişler',
            'orders'        => $result['items'],
            'pager'         => $this->pager($result, $request),
            'filters'       => $inputs,
            'tab'           => $tab,
            'statusCounts'  => (new Order())->countByStatus(),
            'bankPending'   => (int) ($bankPending['total'] ?? 0),
            'statuses'      => self::STATUSES,
            'methods'       => self::PAYMENT_METHODS,
            'paymentStates' => self::PAYMENT_STATUSES,
        ]);
    }

    /** GET /yonetim/siparisler/{orderNumber} (dashboard bağlantıları için) -> sayısal id'ye yönlendirir */
    public function showByNumber(Request $request, string $orderNumber): Response
    {
        $this->require('order.view');

        $order = (new Order())->findByNumber($orderNumber);
        if ($order === null) {
            throw new HttpException(404, 'Sipariş bulunamadı.');
        }

        return $this->redirect('/yonetim/siparisler/' . (int) $order['id']);
    }

    /** GET /yonetim/siparisler/{id} */
    public function show(Request $request, int $id): Response
    {
        $this->require('order.view');

        $order = $this->findOrder($id);
        $allowedNext = (new OrderStatusService())->allowedNext((string) $order['status']);

        return $this->view('admin.orders.show', [
            'title'         => 'Sipariş ' . (string) ($order['order_number'] ?? ('#' . $id)),
            'order'         => $this->prepareOrder($order),
            'customer'      => (new User())->withRole((int) $order['user_id']),
            'items'         => (new OrderItem())->forOrder($id),
            'history'       => (new OrderStatusHistory())->forOrder($id),
            'payments'      => (new Payment())->forOrder($id),
            'shipments'     => (new Shipment())->forOrder($id),
            'allowedNext'   => $allowedNext,
            'statusTargets' => array_values(array_intersect($allowedNext, self::FORM_TARGETS)),
            'carriers'      => $this->carrierNames(),
        ]);
    }

    /** POST /yonetim/siparisler/{id}/durum */
    public function updateStatus(Request $request, int $id): Response
    {
        $this->require('order.update_status');

        $data = $this->validate($request, [
            'to'   => 'required|string|max:30',
            'note' => 'nullable|string|max:255',
        ]);
        $to = (string) $data['to'];
        $note = isset($data['note']) ? trim((string) $data['note']) : '';
        $note = $note === '' ? null : $note;
        $old = ['to' => $to, 'note' => $note ?? ''];

        $order = $this->findOrder($id);
        $from = (string) $order['status'];
        $service = new OrderStatusService();

        if (!in_array($to, $service->allowedNext($from), true) || !in_array($to, self::FORM_TARGETS, true)) {
            throw new ValidationException(['to' => 'Bu durum geçişi yapılamaz.'], $old);
        }

        // İptal ve iade ayrı yetkilerdir; update_status tek başına yetmez (iç tehdit önlemi).
        if ($to === 'cancelled') {
            $this->require('order.cancel');
        } elseif ($to === 'refunded') {
            $this->require('order.refund');
        }

        if (in_array($to, ['cancelled', 'refunded'], true) && ($note === null || mb_strlen($note) < 5)) {
            throw new ValidationException(
                ['note' => 'İptal/iade için en az 5 karakterlik neden zorunludur.'],
                $old
            );
        }

        $adminId = $this->adminId();

        return $this->guarded($id, function () use ($service, $id, $to, $from, $note, $adminId, $order): string {
            $service->transition($id, $to, $adminId, $note);

            AuditService::log('order.status_change', 'order', $id, ['status' => $from], [
                'status'       => $to,
                'note'         => $note,
                'order_number' => $order['order_number'] ?? null,
            ]);

            if ($to === 'cancelled') {
                $this->notifySafely(fn () => (new OrderNotificationService())->sendCancelled($id));
            }
            if (in_array($to, ['cancelled', 'refunded'], true)
                && (string) $order['payment_status'] === 'paid'
                && (string) $order['payment_method'] === 'credit_card'
            ) {
                $this->flash('warning', 'Kart ödemesinin gerçek iadesi PayTR panelinden ayrıca yapılmalıdır.');
            }

            return 'Sipariş durumu güncellendi.';
        });
    }

    /** POST /yonetim/siparisler/{id}/kargo */
    public function addShipment(Request $request, int $id): Response
    {
        $this->require('shipment.manage');

        $data = $this->validate($request, [
            'carrier'         => 'required|string|max:30',
            'tracking_number' => 'required|string|max:40',
            'note'            => 'nullable|string|max:255',
        ]);
        $adminId = $this->adminId();

        return $this->guarded($id, function () use ($id, $data, $adminId): string {
            (new ShipmentService())->addShipment(
                $id,
                (string) $data['carrier'],
                (string) $data['tracking_number'],
                isset($data['note']) ? (string) $data['note'] : null,
                $adminId
            );

            return 'Sipariş kargoya verildi.';
        });
    }

    /** POST /yonetim/siparisler/{id}/kargo/{shipmentId}/guncelle */
    public function updateShipment(Request $request, int $id, int $shipmentId): Response
    {
        $this->require('shipment.manage');

        $data = $this->validate($request, [
            'carrier'         => 'required|string|max:30',
            'tracking_number' => 'required|string|max:40',
            'note'            => 'nullable|string|max:255',
        ]);

        $this->findOrder($id);
        // IDOR: kargo kaydı gerçekten bu siparişe ait olmalı.
        $belongs = false;
        foreach ((new Shipment())->forOrder($id) as $shipment) {
            if ((int) $shipment['id'] === $shipmentId) {
                $belongs = true;
                break;
            }
        }
        if (!$belongs) {
            throw new HttpException(404, 'Kargo kaydı bulunamadı.');
        }

        $adminId = $this->adminId();

        return $this->guarded($id, function () use ($shipmentId, $data, $adminId): string {
            (new ShipmentService())->updateShipment(
                $shipmentId,
                (string) $data['carrier'],
                (string) $data['tracking_number'],
                isset($data['note']) ? (string) $data['note'] : null,
                $adminId
            );

            return 'Kargo bilgisi güncellendi.';
        });
    }

    /** POST /yonetim/siparisler/{id}/teslim */
    public function markDelivered(Request $request, int $id): Response
    {
        $this->require('shipment.manage');

        $this->findOrder($id);
        $adminId = $this->adminId();

        return $this->guarded($id, function () use ($id, $adminId): string {
            (new ShipmentService())->markDelivered($id, $adminId);

            return 'Sipariş teslim edildi olarak işaretlendi.';
        });
    }

    /** POST /yonetim/siparisler/{id}/havale-onay */
    public function confirmBank(Request $request, int $id): Response
    {
        $this->require('payment.confirm');

        $data = $this->validate($request, ['note' => 'nullable|string|max:255']);
        $note = isset($data['note']) ? trim((string) $data['note']) : '';
        $note = $note === '' ? null : $note;

        $order = $this->findOrder($id);
        $adminId = $this->adminId();

        return $this->guarded($id, function () use ($id, $order, $note, $adminId): string {
            if ((string) $order['payment_method'] !== 'bank_transfer') {
                throw new DomainException('Bu sipariş havale/EFT ile ödenmemiş.');
            }
            // Denetim kaydı ve e-posta PaymentService içinde yapılır.
            (new PaymentService())->confirmBankTransfer($id, $adminId, $note);

            return 'Havale/EFT ödemesi onaylandı.';
        });
    }

    /** POST /yonetim/siparisler/{id}/kapida-odeme-alindi */
    public function markCodPaid(Request $request, int $id): Response
    {
        $this->require('payment.confirm');

        $order = $this->findOrder($id);
        $adminId = $this->adminId();

        return $this->guarded($id, function () use ($id, $order, $adminId): string {
            if ((string) $order['payment_method'] !== 'cod') {
                throw new DomainException('Bu sipariş kapıda ödemeli değil.');
            }
            // Denetim kaydı PaymentService içinde yapılır.
            (new PaymentService())->markCodCollected($id, $adminId);

            return 'Kapıda ödeme tahsil edildi olarak işaretlendi.';
        });
    }

    /** GET /yonetim/siparisler/{id}/yazdir */
    public function print(Request $request, int $id): Response
    {
        $this->require('order.view');

        $order = $this->findOrder($id);

        // Yazdırma sayfası kendi başına tam HTML'dir; admin layout'u kullanılmaz.
        return Response::html(View::render('admin.orders.print', [
            'title'     => 'Sipariş ' . (string) ($order['order_number'] ?? ('#' . $id)),
            'order'     => $this->prepareOrder($order),
            'customer'  => (new User())->withRole((int) $order['user_id']),
            'items'     => (new OrderItem())->forOrder($id),
            'shipments' => (new Shipment())->forOrder($id),
            'siteName'  => (string) config('app.name', ''),
        ]));
    }

    /** GET /yonetim/siparisler/disa-aktar (CSV) */
    public function exportCsv(Request $request): Response
    {
        $this->require('order.view');
        $this->require('report.view');

        [$filters, , $inputs] = $this->readFilters($request);

        $header = [
            'Sipariş No', 'Tarih', 'Durum', 'Ödeme Yöntemi', 'Ödeme Durumu', 'Müşteri', 'E-posta',
            'Ara Toplam', 'İndirim', 'Kargo', 'Kapıda Ödeme Ücreti', 'Genel Toplam', 'Kupon', 'Ödeme Tarihi',
        ];

        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new HttpException(500, 'Dışa aktarma hazırlanamadı.');
        }
        fwrite($handle, "\xEF\xBB\xBF"); // Excel'in UTF-8'i tanıması için BOM
        fputcsv($handle, $header, ',', '"', '');

        $order = new Order();
        $count = 0;
        for ($page = 1; $page <= self::CSV_MAX_PAGES; $page++) {
            $result = $order->adminList($filters, $page, 100);
            foreach ($result['items'] as $row) {
                // TC, IBAN ve adres alanları bilerek dışarıda bırakılır.
                fputcsv($handle, array_map([$this, 'csvCell'], [
                    $row['order_number'] ?? '',
                    $row['created_at'] ?? '',
                    $row['status'] ?? '',
                    $row['payment_method'] ?? '',
                    $row['payment_status'] ?? '',
                    trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                    $row['email'] ?? '',
                    $row['subtotal'] ?? '',
                    $row['discount_total'] ?? '',
                    $row['shipping_total'] ?? '',
                    $row['cod_fee'] ?? '',
                    $row['grand_total'] ?? '',
                    $row['coupon_code'] ?? '',
                    $row['paid_at'] ?? '',
                ]), ',', '"', '');
                $count++;
            }
            if ($page >= (int) ($result['pages'] ?? 1)) {
                break;
            }
        }

        rewind($handle);
        $body = (string) stream_get_contents($handle);
        fclose($handle);

        // Toplu veri çıkışı iç tehdit riskidir; kim, hangi filtreyle, kaç satır aldı kaydedilir.
        AuditService::log('order.export_csv', 'order', null, null, [
            'filters'   => $inputs,
            'row_count' => $count,
        ]);

        return Response::text($body)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="siparisler-' . date('Ymd-His') . '.csv"')
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * Eylemi çalıştırır; iş kuralı hatasını flash ile gösterip detay sayfasına döner.
     * ValidationException/HttpException ErrorHandler'a bırakılır.
     */
    private function guarded(int $id, callable $action): Response
    {
        try {
            $message = $action();
            if (is_string($message) && $message !== '') {
                $this->flash('success', $message);
            }
        } catch (DomainException | PaymentException $e) {
            $this->flash('error', $e->getMessage());
        }

        return $this->redirect('/yonetim/siparisler/' . $id);
    }

    /** E-posta hatası ana işlemi bozmaz. */
    private function notifySafely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            \App\Core\Logger::warning('Sipariş e-postası gönderilemedi', ['error' => $e->getMessage()]);
        }
    }

    private function adminId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw new HttpException(401, 'Oturum bulunamadı.');
        }

        return $id;
    }

    /** @return array<string,mixed> */
    private function findOrder(int $id): array
    {
        $order = (new Order())->find($id);
        if ($order === null) {
            throw new HttpException(404, 'Sipariş bulunamadı.');
        }

        return $order;
    }

    /**
     * Adresleri çözer, TC'yi maskeler, ham ikili IP alanlarını metne çevirir.
     *
     * @param array<string,mixed> $order
     * @return array<string,mixed>
     */
    private function prepareOrder(array $order): array
    {
        $order['shipping'] = $this->decodeAddress($order['shipping_address'] ?? null);
        $order['billing'] = $this->decodeAddress($order['billing_address'] ?? null);
        unset($order['shipping_address'], $order['billing_address']);

        $order['consent_ip'] = bin_to_ip(isset($order['consent_ip']) ? (string) $order['consent_ip'] : null);
        $order['ip_address'] = bin_to_ip(isset($order['ip_address']) ? (string) $order['ip_address'] : null);

        return $order;
    }

    /** @return array<string,mixed> */
    private function decodeAddress(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        // TC kimlik no hassastır: admin ekranlarında yalnızca son 2 hane görünür.
        if (isset($data['tc_identity_no']) && is_string($data['tc_identity_no']) && $data['tc_identity_no'] !== '') {
            $tc = $data['tc_identity_no'];
            $data['tc_identity_no'] = str_repeat('*', max(0, strlen($tc) - 2)) . substr($tc, -2);
        }

        return $data;
    }

    /** @return array<string,string> kod => görünen ad */
    private function carrierNames(): array
    {
        $names = [];
        $carriers = config('carriers', []);
        if (is_array($carriers)) {
            foreach ($carriers as $key => $item) {
                if (is_array($item) && isset($item['name'])) {
                    $names[(string) $key] = (string) $item['name'];
                }
            }
        }

        return $names;
    }

    /**
     * Sorgu parametrelerini beyaz listeyle süzer.
     *
     * @return array{0:array<string,mixed>,1:string,2:array<string,string>} [model filtresi, sekme, form değerleri]
     */
    private function readFilters(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        $q = mb_substr($q, 0, 100);

        $status = (string) $request->query('status', '');
        $method = (string) $request->query('payment_method', '');
        $pstatus = (string) $request->query('payment_status', '');
        $tab = (string) $request->query('tab', 'all');

        $inputs = [
            'q'              => $q,
            'status'         => in_array($status, self::STATUSES, true) ? $status : '',
            'payment_method' => in_array($method, self::PAYMENT_METHODS, true) ? $method : '',
            'payment_status' => in_array($pstatus, self::PAYMENT_STATUSES, true) ? $pstatus : '',
            'date_from'      => $this->validDate((string) $request->query('date_from', '')),
            'date_to'        => $this->validDate((string) $request->query('date_to', '')),
        ];
        $tab = in_array($tab, self::TABS, true) ? $tab : 'all';

        $filters = array_filter($inputs, static fn (string $v): bool => $v !== '');

        // Sekme, ilgili filtreleri belirler ve elle girilen değerin üzerine yazar.
        if ($tab === 'bank_pending') {
            $filters['status'] = 'pending_payment';
            $filters['payment_method'] = 'bank_transfer';
        } elseif ($tab === 'to_prepare') {
            $filters['status'] = 'paid';
        } elseif ($tab === 'shipped') {
            $filters['status'] = 'shipped';
        }

        return [$filters, $tab, $inputs];
    }

    private function validDate(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        ) {
            return $value;
        }

        return '';
    }

    /** CSV formül enjeksiyonu: tehlikeli ilk karakterin başına ' eklenir. */
    private function csvCell(mixed $value): string
    {
        $text = (string) $value;
        if ($text !== '' && in_array($text[0], self::CSV_FORMULA_CHARS, true)) {
            return "'" . $text;
        }

        return $text;
    }
}