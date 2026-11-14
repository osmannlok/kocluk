<?php
// app/Services/Payment/PaymentService.php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Helpers\Money;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\OrderNotificationService;
use App\Services\OrderStatusService;
use DomainException;
use Throwable;

/**
 * Ödeme iş kuralları: kart ödemesini başlatma, PayTR bildirimi, havale onayı, kapıda tahsilat.
 */
final class PaymentService
{
    private OrderStatusService $statusService;
    private OrderNotificationService $notifier;
    private ?PayTRGateway $paytr = null;

    public function __construct(?OrderStatusService $statusService = null, ?OrderNotificationService $notifier = null)
    {
        $this->statusService = $statusService ?? new OrderStatusService();
        $this->notifier = $notifier ?? new OrderNotificationService();
    }

    public function gatewayFor(string $method): PaymentGatewayInterface
    {
        return match ($method) {
            'credit_card', 'paytr' => $this->paytr(),
            'bank_transfer'        => new BankTransferGateway(),
            'cod'                  => new CodGateway(),
            default                => throw new PaymentException('Geçersiz ödeme yöntemi.'),
        };
    }

    /**
     * Kart ödeme sayfası için yeni PayTR token'ı üretir (aynı merchant_oid, her çağrıda yeni token).
     *
     * @return array{token:string,order:array<string,mixed>}
     */
    public function startCardPayment(string $orderNumber, int $userId): array
    {
        // IDOR: sipariş her zaman user_id ile sınırlı okunur.
        $order = (new Order())->findForUser($orderNumber, $userId);
        if ($order === null) {
            throw new HttpException(404, 'Sipariş bulunamadı.');
        }

        if ((string) $order['payment_method'] !== 'credit_card') {
            throw new DomainException('Bu sipariş kart ile ödenmiyor.');
        }
        if ((string) $order['status'] !== 'pending_payment') {
            throw new DomainException('Bu sipariş için ödeme beklenmiyor.');
        }
        if ($this->isExpired($order)) {
            throw new DomainException('Bu siparişin ödeme süresi dolmuş.');
        }

        $orderId = (int) $order['id'];
        $payment = (new Payment())->latestForOrder($orderId);
        // Yalnızca açık (initiated/pending) kart ödemesi yeniden denenebilir; kapanmış kayıtta çift tahsilat riski vardır.
        if (
            $payment === null
            || (string) $payment['provider'] !== 'paytr'
            || !in_array((string) $payment['status'], ['initiated', 'pending'], true)
        ) {
            throw new DomainException('Bu sipariş için açık bir kart ödemesi bulunmuyor.');
        }

        $user = (new User())->find($userId);
        if ($user === null) {
            throw new HttpException(404, 'Kullanıcı bulunamadı.');
        }

        $items = (new OrderItem())->forOrder($orderId);
        $result = $this->gatewayFor('credit_card')->initiate($order, $payment, $user, $items);

        $token = $result['token'] ?? null;
        if (($result['type'] ?? '') !== 'iframe' || !is_string($token) || $token === '') {
            throw new PaymentException('Ödeme sayfası oluşturulamadı. Lütfen tekrar deneyin.');
        }

        return ['token' => $token, 'order' => $order];
    }

    /**
     * PayTR bildirimi (2. adım). YALNIZCA 'OK' veya hata metni döner.
     * Ödeme sonucu sadece burada belirlenir; müşteri yönlendirme sayfaları durumu değiştirmez.
     *
     * @param array<string,mixed> $post
     */
    public function handlePaytrCallback(array $post, string $ipBin): string
    {
        $oid = $this->cleanOid($post['merchant_oid'] ?? null);
        $hashValid = false;
        $result = null;
        $notify = null;
        $orderId = 0;
        $response = 'OK';

        try {
            $hashValid = $this->paytr()->verifyCallbackHash($post);

            if (!$hashValid) {
                // Sahte/bozuk bildirim: hiçbir işlem yapılmaz.
                $result = 'bad_hash';
                SecurityEvent::record('bad_paytr_hash', 'critical', null, bin_to_ip($ipBin), [
                    'merchant_oid' => $oid,
                ]);
                $response = 'PAYTR notification failed: bad hash';
            } else {
                $outcome = Database::instance()->transaction(
                    fn (): array => $this->applyCallback($oid, $post, $ipBin)
                );
                $result = $outcome['result'];
                $notify = $outcome['notify'];
                $orderId = $outcome['order_id'];
            }
        } catch (Throwable $e) {
            // Hata: 'OK' dönmeyiz ki PayTR bildirimi tekrar denesin. Ayrıntı yalnızca loga.
            $result = 'error';
            Logger::error('PayTR bildirimi işlenemedi.', [
                'merchant_oid' => $oid,
                'exception'    => get_class($e),
                'error'        => $e->getMessage(),
            ]);
            $response = 'PAYTR notification failed: server error';
        }

        // payment_callbacks eklemelidir (UPDATE yok): sonuç belli olunca tek satır yazılır, transaction dışında.
        try {
            (new PaymentCallback())->log($oid, $this->payloadJson($post), $hashValid, $ipBin, $result);
        } catch (Throwable $e) {
            Logger::error('PayTR bildirim günlüğü yazılamadı.', ['error' => $e->getMessage()]);
        }

        // E-postalar transaction dışında; hata ödeme sonucunu etkilemez.
        if ($notify !== null && $orderId > 0) {
            if ($notify === 'paid') {
                $this->notifier->sendPaid($orderId);
            } elseif ($notify === 'cancelled') {
                $this->notifier->sendCancelled($orderId);
            }
        }

        return $response;
    }

    /**
     * Admin dekont kontrolüyle havale/EFT ödemesini onaylar.
     */
    public function confirmBankTransfer(int $orderId, int $adminId, ?string $note): void
    {
        $note = $this->cleanNote($note, 150);

        Database::instance()->transaction(function () use ($orderId, $adminId, $note): void {
            $orders = new Order();
            $order = $orders->findByIdForUpdate($orderId);
            if ($order === null) {
                throw new DomainException('Sipariş bulunamadı.');
            }
            if ((string) $order['payment_method'] !== 'bank_transfer') {
                throw new DomainException('Bu sipariş havale/EFT ile ödenmiyor.');
            }
            if ((string) $order['status'] !== 'pending_payment') {
                throw new DomainException('Yalnızca ödeme bekleyen siparişler onaylanabilir.');
            }

            $payments = new Payment();
            $payment = $payments->latestForOrder($orderId);
            if (
                $payment === null
                || (string) $payment['provider'] !== 'bank_transfer'
                || !in_array((string) $payment['status'], ['initiated', 'pending'], true)
            ) {
                throw new DomainException('Bekleyen havale/EFT ödeme kaydı bulunamadı.');
            }

            $payments->confirmManual((int) $payment['id'], $adminId, $note);
            $this->statusService->transition(
                $orderId,
                'paid',
                $adminId,
                'Havale/EFT dekont onayı' . ($note !== null ? ': ' . $note : '')
            );

            $this->audit($adminId, 'payment.confirm_bank_transfer', $orderId, [
                'status'         => (string) $order['status'],
                'payment_status' => (string) $order['payment_status'],
            ], [
                'status'         => 'paid',
                'payment_status' => 'paid',
                'payment_id'     => (int) $payment['id'],
                'note'           => $note,
            ]);
        });

        $this->notifier->sendPaid($orderId);
    }

    /**
     * Kapıda ödemenin tahsil edildiğini işaretler. Sipariş durumu DEĞİŞMEZ.
     */
    public function markCodCollected(int $orderId, int $adminId): void
    {
        Database::instance()->transaction(function () use ($orderId, $adminId): void {
            $orders = new Order();
            $order = $orders->findByIdForUpdate($orderId);
            if ($order === null) {
                throw new DomainException('Sipariş bulunamadı.');
            }
            if ((string) $order['payment_method'] !== 'cod') {
                throw new DomainException('Bu sipariş kapıda ödeme değil.');
            }
            if (!in_array((string) $order['status'], ['preparing', 'shipped', 'delivered'], true)) {
                throw new DomainException('Bu sipariş durumunda tahsilat işaretlenemez.');
            }
            if ((string) $order['payment_status'] !== 'pending') {
                throw new DomainException('Bu siparişin ödemesi zaten işlenmiş.');
            }

            $payments = new Payment();
            $payment = $payments->latestForOrder($orderId);
            if (
                $payment === null
                || (string) $payment['provider'] !== 'cod'
                || !in_array((string) $payment['status'], ['initiated', 'pending'], true)
            ) {
                throw new DomainException('Bekleyen kapıda ödeme kaydı bulunamadı.');
            }

            $payments->confirmManual((int) $payment['id'], $adminId, 'Kapıda ödeme tahsil edildi');
            $orders->updateStatusFields($orderId, [
                'payment_status' => 'paid',
                'paid_at'        => now(),
            ]);

            $this->audit($adminId, 'payment.cod_collected', $orderId, [
                'payment_status' => (string) $order['payment_status'],
            ], [
                'payment_status' => 'paid',
                'payment_id'     => (int) $payment['id'],
            ]);
        });
    }

    /**
     * Bildirim gövdesi (transaction içinde). Kilit sırası: sipariş, sonra payment
     * (OrderStatusService ile aynı sıra, deadlock önlenir).
     *
     * @param array<string,mixed> $post
     * @return array{result:string,notify:?string,order_id:int}
     */
    private function applyCallback(?string $oid, array $post, string $ipBin): array
    {
        $none = static fn (string $result, int $orderId = 0): array => [
            'result' => $result, 'notify' => null, 'order_id' => $orderId,
        ];

        if ($oid === null) {
            Logger::warning('PayTR bildirimi: geçersiz merchant_oid biçimi.');
            return $none('unknown_order');
        }

        $payments = new Payment();
        $peek = $payments->findByMerchantOid($oid);
        if ($peek === null) {
            // Bilinmeyen oid: logla ve 'OK' dön (PayTR sonsuza kadar tekrar denemesin).
            Logger::warning('PayTR bildirimi: bilinmeyen merchant_oid.', ['merchant_oid' => $oid]);
            return $none('unknown_order');
        }

        $order = (new Order())->findByIdForUpdate((int) $peek['order_id']);
        $payment = $payments->findByMerchantOid($oid, true);
        if ($order === null || $payment === null) {
            Logger::warning('PayTR bildirimi: sipariş/ödeme kaydı bulunamadı.', ['merchant_oid' => $oid]);
            return $none('unknown_order');
        }

        $orderId = (int) $order['id'];
        $userId = (int) $order['user_id'];
        $ip = bin_to_ip($ipBin);
        $paytrStatus = (string) ($post['status'] ?? '');
        $totalRaw = (string) ($post['total_amount'] ?? '');
        $isSuccess = $paytrStatus === 'success';

        // İDEMPOTENT: ödeme zaten kapanmışsa tekrar işlem yapılmaz.
        if (!in_array((string) $payment['status'], ['initiated', 'pending'], true)) {
            if ($isSuccess && (string) $payment['status'] === 'failed') {
                // Başarısız kapanmış ödemeye sonradan başarı gelmesi para riski taşır; yöneticiye bildir.
                SecurityEvent::record('late_payment_on_closed_order', 'critical', $userId, $ip, [
                    'merchant_oid' => $oid,
                    'total_amount' => $totalRaw,
                    'reason'       => 'payment_already_failed',
                ]);
            }
            return $none('duplicate', $orderId);
        }

        if ($isSuccess) {
            $expectedCents = Money::toCents((string) $order['grand_total']);
            $receivedCents = (ctype_digit($totalRaw) && strlen($totalRaw) <= 12) ? (int) $totalRaw : -1;

            // Tutar siparişle BİREBİR eşleşmezse sipariş ödendi sayılmaz.
            if ($receivedCents !== $expectedCents) {
                SecurityEvent::record('amount_mismatch', 'critical', $userId, $ip, [
                    'merchant_oid'   => $oid,
                    'order_number'   => (string) $order['order_number'],
                    'expected_cents' => $expectedCents,
                    'received_cents' => $receivedCents,
                ]);
                $payments->markFailed((int) $payment['id'], 'amount_mismatch', 'Bildirim tutarı sipariş tutarıyla eşleşmedi.');
                return $none('amount_mismatch', $orderId);
            }

            $extra = [
                'payment_type'        => $this->cleanShort($post['payment_type'] ?? null, 20),
                'installment_count'   => $this->installment($post['installment_count'] ?? null),
                'note'                => null,
                'callback_received_at' => now(),
            ];

            // Sipariş kapanmışsa (ör. cron iptal etti) durum DEĞİŞTİRİLMEZ; DomainException atılmaz,
            // aksi halde PayTR bildirimi sonsuza kadar tekrar dener.
            if ((string) $order['status'] !== 'pending_payment') {
                $extra['note'] = 'Kapanmış siparişe geç ödeme geldi.';
                $payments->markSuccess((int) $payment['id'], $extra);
                SecurityEvent::record('late_payment_on_closed_order', 'critical', $userId, $ip, [
                    'merchant_oid'  => $oid,
                    'order_number'  => (string) $order['order_number'],
                    'order_status'  => (string) $order['status'],
                    'amount_cents'  => $receivedCents,
                ]);
                return $none('late_payment', $orderId);
            }

            $payments->markSuccess((int) $payment['id'], $extra);
            $this->statusService->transition($orderId, 'paid', null, 'PayTR ödeme onayı');

            return ['result' => 'processed', 'notify' => 'paid', 'order_id' => $orderId];
        }

        // Başarısız ödeme
        $code = $this->cleanShort($post['failed_reason_code'] ?? null, 20);
        $msg = $this->cleanShort($post['failed_reason_msg'] ?? null, 255);
        $payments->markFailed((int) $payment['id'], $code, $msg);

        if ((string) $order['status'] === 'pending_payment') {
            // İptal: stok iadesi ve kupon geri alma OrderStatusService içinde yapılır.
            $this->statusService->transition(
                $orderId,
                'cancelled',
                null,
                'PayTR: ödeme başarısız' . ($code !== null ? ' (' . $code . ')' : '')
            );
            return ['result' => 'processed', 'notify' => 'cancelled', 'order_id' => $orderId];
        }

        return $none('processed', $orderId);
    }

    private function paytr(): PayTRGateway
    {
        return $this->paytr ??= new PayTRGateway();
    }

    /**
     * @param array<string,mixed> $order
     */
    private function isExpired(array $order): bool
    {
        $expires = $order['expires_at'] ?? null;
        if ($expires === null || $expires === '') {
            return false;
        }
        $ts = strtotime((string) $expires);

        return $ts !== false && $ts <= time();
    }

    private function cleanOid(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return ($value !== '' && strlen($value) <= 64 && preg_match('/^[A-Za-z0-9]+$/', $value) === 1) ? $value : null;
    }

    private function cleanShort(mixed $value, int $max): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim((string) preg_replace('/\p{Cc}+/u', ' ', mb_scrub($value, 'UTF-8')));

        return $value === '' ? null : mb_substr($value, 0, $max, 'UTF-8');
    }

    private function cleanNote(?string $note, int $max): ?string
    {
        return $note === null ? null : $this->cleanShort($note, $max);
    }

    private function installment(mixed $value): ?int
    {
        if (!is_string($value) || !ctype_digit($value) || strlen($value) > 3) {
            return null;
        }
        $n = (int) $value;

        return ($n >= 0 && $n <= 255) ? $n : null;
    }

    /**
     * @param array<string,mixed> $post
     */
    private function payloadJson(array $post): string
    {
        $safe = [];
        foreach (array_slice($post, 0, 40, true) as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $safe[mb_substr((string) $key, 0, 60)] = mb_substr((string) $value, 0, 500);
            }
        }
        $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? '{}' : $json;
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,mixed> $new
     */
    private function audit(int $adminId, string $action, int $orderId, array $old, array $new): void
    {
        $request = Request::capture();
        (new AuditLog())->write(
            $adminId,
            $action,
            'order',
            $orderId,
            $old,
            $new,
            ip_to_bin($request->ip()),
            $request->userAgent()
        );
    }
}