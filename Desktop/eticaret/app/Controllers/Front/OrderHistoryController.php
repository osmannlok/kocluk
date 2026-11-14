<?php
// app/Controllers/Front/OrderHistoryController.php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Services\AuditService;
use App\Services\OrderNotificationService;
use App\Services\OrderStatusService;
use DomainException;
use Throwable;

/**
 * Müşterinin kendi sipariş geçmişi: liste, detay, iptal.
 * Tüm sorgular Auth::id() ile sınırlıdır (IDOR koruması: Order::findForUser).
 */
final class OrderHistoryController extends BaseController
{
    /** @var array<string,array{0:string,1:string}> durum => [etiket, Bootstrap rozet sınıfı] */
    public const STATUS_MAP = [
        'pending_payment' => ['Ödeme bekleniyor', 'text-bg-warning'],
        'paid'            => ['Ödendi', 'text-bg-info'],
        'preparing'       => ['Hazırlanıyor', 'text-bg-primary'],
        'shipped'         => ['Kargoya verildi', 'text-bg-info'],
        'delivered'       => ['Teslim edildi', 'text-bg-success'],
        'cancelled'       => ['İptal edildi', 'text-bg-danger'],
        'refunded'        => ['İade edildi', 'text-bg-secondary'],
    ];

    /** @var array<string,string> */
    public const PAYMENT_METHODS = [
        'credit_card'   => 'Kredi / Banka Kartı',
        'bank_transfer' => 'Havale / EFT',
        'cod'           => 'Kapıda Ödeme',
    ];

    /** @var array<string,array{0:string,1:string}> */
    public const PAYMENT_STATUSES = [
        'pending'  => ['Bekleniyor', 'text-bg-warning'],
        'paid'     => ['Ödendi', 'text-bg-success'],
        'failed'   => ['Başarısız', 'text-bg-danger'],
        'refunded' => ['İade edildi', 'text-bg-secondary'],
    ];

    private const PER_PAGE = 10;
    private const ORDER_NUMBER_PATTERN = '/^YM-\d{4}-\d{6,}$/';

    /** Sipariş listesi (sayfalı). */
    public function index(Request $request): Response
    {
        $userId = $this->userId();

        $rawPage = $request->query('page', 1);
        $page = is_scalar($rawPage) ? max(1, (int) $rawPage) : 1;

        $result = (new Order())->listForUser($userId, $page, self::PER_PAGE);

        $orders = [];
        $thumbs = [];
        $imageCache = [];
        foreach ($result['items'] as $row) {
            $orders[] = self::decorate($row);
            $thumbs[(int) $row['id']] = $this->thumbnails((int) $row['id'], $imageCache);
        }

        View::share('scripts', ['account.js']);

        return $this->view('front.account.orders', [
            'title'           => 'Siparişlerim',
            'metaDescription' => 'Geçmiş siparişleriniz ve durumları.',
            'orders'          => $orders,
            'thumbs'          => $thumbs,
            'pager'           => [
                'page'  => (int) $result['page'],
                'pages' => (int) $result['pages'],
                'total' => (int) $result['total'],
            ],
        ], 'layouts.account');
    }

    /** Sipariş detayı. */
    public function show(Request $request, string $orderNumber): Response
    {
        $userId = $this->userId();
        $order = $this->findOwned($orderNumber, $userId);
        $orderId = (int) $order['id'];

        $items = (new OrderItem())->forOrder($orderId);

        $history = (new OrderStatusHistory())->forOrder($orderId);
        usort($history, static function (array $a, array $b): int {
            return [(string) ($a['created_at'] ?? ''), (int) ($a['id'] ?? 0)]
                <=> [(string) ($b['created_at'] ?? ''), (int) ($b['id'] ?? 0)];
        });

        // Müşteriye yalnızca durum ve zaman gösterilir; personel adı/iç not sızmaz.
        $historyRows = [];
        foreach ($history as $h) {
            $to = (string) ($h['to_status'] ?? '');
            $map = self::STATUS_MAP[$to] ?? [$to, 'text-bg-secondary'];
            $historyRows[] = [
                'label' => $map[0],
                'class' => $map[1],
                'at'    => self::formatDate($h['created_at'] ?? null),
            ];
        }

        $shipments = [];
        foreach ((new Shipment())->forOrder($orderId) as $s) {
            $url = (string) ($s['tracking_url'] ?? '');
            // Savunma: yalnızca http/https takip bağlantısı gösterilir.
            if ($url !== '' && preg_match('#^https?://#i', $url) !== 1) {
                $url = '';
            }
            $shipments[] = [
                'carrier'         => (string) ($s['carrier'] ?? ''),
                'tracking_number' => (string) ($s['tracking_number'] ?? ''),
                'tracking_url'    => $url,
                'shipped_at'      => self::formatDate($s['shipped_at'] ?? null),
                'delivered_at'    => self::formatDate($s['delivered_at'] ?? null),
            ];
        }

        $payment = (new Payment())->latestForOrder($orderId);
        $installments = ($payment !== null && (int) ($payment['installment_count'] ?? 0) > 1)
            ? (int) $payment['installment_count']
            : 0;

        $status = (string) $order['status'];
        $method = (string) $order['payment_method'];

        $instructions = null;
        if ($method === 'bank_transfer' && $status === 'pending_payment') {
            $instructions = (new OrderNotificationService())->paymentInstructions($order);
        }

        // Kart ile ödeme: bekliyorsa ve süre geçmediyse tekrar ödeme bağlantısı.
        $payUrl = null;
        $expiresAt = (string) ($order['expires_at'] ?? '');
        if ($method === 'credit_card' && $status === 'pending_payment' && $expiresAt !== '') {
            $ts = strtotime($expiresAt);
            if ($ts !== false && $ts > time()) {
                $payUrl = '/odeme/kart/' . rawurlencode((string) $order['order_number']);
            }
        }

        $shippingAddress = $this->addressView($this->decodeAddress($order['shipping_address'] ?? null));
        $billingRaw = $this->decodeAddress($order['billing_address'] ?? null);
        $billing = $this->addressView($billingRaw);
        $billing['type'] = (string) ($billingRaw['invoice_type'] ?? $billingRaw['type'] ?? 'individual');
        $billing['tc_identity_no'] = self::maskIdentity((string) ($billingRaw['tc_identity_no'] ?? ''));
        $billing['company_name'] = (string) ($billingRaw['company_name'] ?? '');
        $billing['tax_office'] = (string) ($billingRaw['tax_office'] ?? '');
        $billing['tax_number'] = (string) ($billingRaw['tax_number'] ?? '');

        $decorated = self::decorate($order);

        View::share('scripts', ['account.js']);

        return $this->view('front.account.order-show', [
            'title'           => 'Sipariş ' . (string) $order['order_number'],
            'metaDescription' => 'Sipariş detayı ve kargo takibi.',
            'order'           => $decorated,
            'items'           => $items,
            'steps'           => $this->buildSteps($order, $history),
            'historyRows'     => $historyRows,
            'shipments'       => $shipments,
            'installments'    => $installments,
            'instructions'    => $instructions,
            'payUrl'          => $payUrl,
            'shippingAddress' => $shippingAddress,
            'billing'         => $billing,
            'canCancel'       => $this->isCancellable($order),
        ], 'layouts.account');
    }

    /** Müşteri iptali: yalnızca pending_payment veya paid (henüz preparing değil). */
    public function cancel(Request $request, string $orderNumber): Response
    {
        $userId = $this->userId();
        $order = $this->findOwned($orderNumber, $userId);
        $orderId = (int) $order['id'];
        $path = '/hesabim/siparisler/' . rawurlencode((string) $order['order_number']);

        if (!$this->isCancellable($order)) {
            $this->flash('warning', 'Bu sipariş artık çevrimiçi iptal edilemez. İptal için lütfen bizimle iletişime geçin.');
            return $this->redirect($path);
        }

        $oldStatus = (string) $order['status'];

        try {
            // Geçerlilik ve stok/kupon iadesi durum makinesinde, tek transaction içinde yapılır.
            (new OrderStatusService())->transition($orderId, 'cancelled', $userId, 'Müşteri tarafından iptal edildi.');
        } catch (DomainException) {
            $this->flash('error', 'Sipariş durumu değiştiği için iptal edilemedi. Lütfen bizimle iletişime geçin.');
            return $this->redirect($path);
        }

        AuditService::log(
            'order.cancel_by_customer',
            'order',
            $orderId,
            ['status' => $oldStatus],
            ['status' => 'cancelled']
        );

        try {
            (new OrderNotificationService())->sendCancelled($orderId);
        } catch (Throwable $e) {
            Logger::warning('İptal e-postası gönderilemedi', ['order_id' => $orderId]);
        }

        if ($oldStatus === 'paid') {
            $this->flash('success', 'Siparişiniz iptal edildi. Ödemenizin iadesi için sizinle iletişime geçeceğiz.');
        } else {
            $this->flash('success', 'Siparişiniz iptal edildi.');
        }

        return $this->redirect($path);
    }

    /**
     * Liste/detay için etiket, rozet ve biçimli tarih ekler; ham IP alanlarını çıkarır.
     *
     * @param array<string,mixed> $order
     * @return array<string,mixed>
     */
    public static function decorate(array $order): array
    {
        $status = (string) ($order['status'] ?? '');
        $map = self::STATUS_MAP[$status] ?? [$status, 'text-bg-secondary'];
        $order['status_label'] = $map[0];
        $order['status_class'] = $map[1];

        $pStatus = (string) ($order['payment_status'] ?? '');
        $pMap = self::PAYMENT_STATUSES[$pStatus] ?? [$pStatus, 'text-bg-secondary'];
        $order['payment_status_label'] = $pMap[0];
        $order['payment_status_class'] = $pMap[1];

        $method = (string) ($order['payment_method'] ?? '');
        $order['payment_method_label'] = self::PAYMENT_METHODS[$method] ?? $method;

        $order['date_fmt'] = self::formatDate($order['created_at'] ?? null);
        $order['cancelled_at_fmt'] = self::formatDate($order['cancelled_at'] ?? null);
        $order['expires_at_fmt'] = self::formatDate($order['expires_at'] ?? null);

        // Ham ikili IP ve tarayıcı bilgisi görünüme taşınmaz.
        unset($order['consent_ip'], $order['consent_user_agent'], $order['ip_address']);

        return $order;
    }

    public static function formatDate(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }
        $ts = strtotime($value);

        return $ts === false ? '' : date('d.m.Y H:i', $ts);
    }

    private static function maskIdentity(string $tc): string
    {
        $len = strlen($tc);
        if ($len < 5) {
            return $len === 0 ? '' : str_repeat('*', $len);
        }

        return substr($tc, 0, 3) . str_repeat('*', $len - 5) . substr($tc, -2);
    }

    private function userId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw new HttpException(401, 'Oturum açmanız gerekiyor.');
        }

        return $id;
    }

    /** @return array<string,mixed> */
    private function findOwned(string $orderNumber, int $userId): array
    {
        if (preg_match(self::ORDER_NUMBER_PATTERN, $orderNumber) !== 1) {
            throw new HttpException(404, 'Sipariş bulunamadı.');
        }
        // IDOR: sipariş her zaman user_id ile birlikte sorgulanır.
        $order = (new Order())->findForUser($orderNumber, $userId);
        if ($order === null) {
            throw new HttpException(404, 'Sipariş bulunamadı.');
        }

        return $order;
    }

    /** @param array<string,mixed> $order */
    private function isCancellable(array $order): bool
    {
        return in_array((string) ($order['status'] ?? ''), ['pending_payment', 'paid'], true);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeAddress(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Yalnızca görüntülenecek alanlar (TC vb. hassas alanlar dışarıda).
     *
     * @param array<string,mixed> $a
     * @return array<string,string>
     */
    private function addressView(array $a): array
    {
        $out = [];
        foreach (['full_name', 'phone', 'city', 'district', 'neighborhood', 'address_line', 'postal_code'] as $key) {
            $out[$key] = (string) ($a[$key] ?? '');
        }

        return $out;
    }

    /**
     * Adımlı durum çizelgesi; iptal/iade durumlarında boş döner.
     *
     * @param array<string,mixed> $order
     * @param array<int,array<string,mixed>> $history
     * @return list<array{label:string,state:string,at:string}>
     */
    private function buildSteps(array $order, array $history): array
    {
        $status = (string) $order['status'];
        if ($status === 'cancelled' || $status === 'refunded') {
            return [];
        }

        $flow = [
            ['received', 'Sipariş alındı'],
            ['paid', 'Ödeme onaylandı'],
            ['preparing', 'Hazırlanıyor'],
            ['shipped', 'Kargoya verildi'],
            ['delivered', 'Teslim edildi'],
        ];
        if ((string) $order['payment_method'] === 'cod') {
            $flow = array_values(array_filter($flow, static fn(array $s): bool => $s[0] !== 'paid'));
        }

        $times = [];
        foreach ($history as $h) {
            $to = (string) ($h['to_status'] ?? '');
            if ($to !== '' && !isset($times[$to])) {
                $times[$to] = (string) ($h['created_at'] ?? '');
            }
        }
        $times['received'] = (string) ($order['created_at'] ?? '');

        $currentKey = $status === 'pending_payment' ? 'received' : $status;
        $keys = array_column($flow, 0);
        $currentIdx = array_search($currentKey, $keys, true);
        if ($currentIdx === false) {
            $currentIdx = 0;
        }

        $steps = [];
        foreach ($flow as $i => [$key, $label]) {
            if ($status === 'delivered' || $i < $currentIdx) {
                $state = 'done';
            } elseif ($i === $currentIdx) {
                $state = 'current';
            } else {
                $state = 'todo';
            }
            $steps[] = [
                'label' => $label,
                'state' => $state,
                'at'    => $state === 'todo' ? '' : self::formatDate($times[$key] ?? null),
            ];
        }

        return $steps;
    }

    /**
     * Sipariş kartı için en çok 3 kalemin adı ve küçük görseli.
     *
     * @param array<int,?string> $imageCache productId => yol
     * @return list<array{name:string,image:?string}>
     */
    private function thumbnails(int $orderId, array &$imageCache): array
    {
        $items = array_slice((new OrderItem())->forOrder($orderId), 0, 3);
        if ($items === []) {
            return [];
        }

        $variantIds = array_values(array_unique(array_map(
            static fn(array $i): int => (int) $i['variant_id'],
            $items
        )));
        $variants = (new ProductVariant())->findActiveMany($variantIds);

        $out = [];
        foreach ($items as $item) {
            $vid = (int) $item['variant_id'];
            $image = null;
            $productId = isset($variants[$vid]) ? (int) $variants[$vid]['product_id'] : 0;
            if ($productId > 0) {
                if (!array_key_exists($productId, $imageCache)) {
                    $imageCache[$productId] = $this->primaryImage($productId);
                }
                $image = $imageCache[$productId];
            }
            $out[] = ['name' => (string) $item['product_name'], 'image' => $image];
        }

        return $out;
    }

    private function primaryImage(int $productId): ?string
    {
        $images = (new ProductImage())->forProduct($productId);
        if ($images === []) {
            return null;
        }
        $chosen = $images[0];
        foreach ($images as $img) {
            if (!empty($img['is_primary'])) {
                $chosen = $img;
                break;
            }
        }
        $path = (string) ($chosen['path'] ?? '');

        // Yalnızca site içi mutlak yol; CSP gereği dış görsel yok.
        return preg_match('#^/(?!/)\S*$#', $path) === 1 ? $path : null;
    }
}