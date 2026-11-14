<?php // app/Services/OrderStatusService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use DomainException;

/**
 * Sipariş durum makinesi. Tüm geçişler tek transaction'da, satır kilidiyle yapılır.
 *
 * NOT: Gerçek ödeme iadesi PayTR panelinden elle yapılır; "refunded" yalnızca kayıt amaçlıdır.
 */
final class OrderStatusService
{
    private const TRANSITIONS = [
        'pending_payment' => ['paid', 'cancelled'],
        'paid' => ['preparing', 'cancelled', 'refunded'],
        'preparing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    private Order $orders;
    private OrderItem $items;
    private OrderStatusHistory $history;
    private Payment $payments;
    private Coupon $coupons;
    private CouponUsage $couponUsages;
    private StockService $stock;

    public function __construct()
    {
        $this->orders = new Order();
        $this->items = new OrderItem();
        $this->history = new OrderStatusHistory();
        $this->payments = new Payment();
        $this->coupons = new Coupon();
        $this->couponUsages = new CouponUsage();
        $this->stock = new StockService();
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return list<string> */
    public function allowedNext(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public function transition(int $orderId, string $to, ?int $userId, ?string $note = null): void
    {
        Database::instance()->transaction(function () use ($orderId, $to, $userId, $note): void {
            // Satır kilidi: aynı siparişe eşzamanlı iki geçiş (ör. PayTR bildirimi + iptal) sıraya girer.
            $order = $this->orders->findByIdForUpdate($orderId);
            if ($order === null) {
                throw new DomainException('Sipariş bulunamadı.');
            }

            $from = (string) $order['status'];
            if (!$this->canTransition($from, $to)) {
                throw new DomainException("Sipariş durumu '{$from}' iken '{$to}' durumuna geçirilemez.");
            }

            $now = now();
            $fields = ['status' => $to];
            $cleanNote = $note !== null ? trim($note) : '';

            switch ($to) {
                case 'paid':
                    $fields['payment_status'] = 'paid';
                    $fields['paid_at'] = $now;
                    break;

                case 'shipped':
                    $fields['shipped_at'] = $now;
                    break;

                case 'delivered':
                    $fields['delivered_at'] = $now;
                    break;

                case 'refunded':
                    $fields['payment_status'] = 'refunded';
                    break;

                case 'cancelled':
                    $fields['cancelled_at'] = $now;
                    $fields['cancel_reason'] = $cleanNote !== '' ? mb_substr($cleanNote, 0, 255) : null;
                    if ((string) $order['payment_status'] === 'pending') {
                        $fields['payment_status'] = 'failed';
                        $this->failOpenPayments($orderId);
                    }
                    $this->releaseResources($order, $userId);
                    break;
            }

            $this->orders->updateStatusFields($orderId, $fields);
            $this->history->add($orderId, $from, $to, $userId, $cleanNote !== '' ? mb_substr($cleanNote, 0, 255) : null);

            AuditService::log(
                'order.status_changed',
                'order',
                $orderId,
                ['status' => $from],
                ['status' => $to, 'note' => $cleanNote !== '' ? $cleanNote : null]
            );
        });
    }

    /** İptalde stok + kupon kullanımı geri alınır (aynı transaction). */
    private function releaseResources(array $order, ?int $userId): void
    {
        $orderId = (int) $order['id'];

        $qty = [];
        foreach ($this->items->forOrder($orderId) as $item) {
            $variantId = (int) $item['variant_id'];
            $qty[$variantId] = ($qty[$variantId] ?? 0) + (int) $item['quantity'];
        }
        $this->stock->release($qty, 'order', $orderId, $userId);

        if (!empty($order['coupon_id'])) {
            $this->couponUsages->deleteByOrder($orderId);
            $this->coupons->decrementUsed((int) $order['coupon_id']);
        }
    }

    /** Bekleyen ödeme kayıtlarını başarısız işaretler (markFailed yalnızca initiated/pending'e dokunur). */
    private function failOpenPayments(int $orderId): void
    {
        foreach ($this->payments->forOrder($orderId) as $payment) {
            if (in_array((string) $payment['status'], ['initiated', 'pending'], true)) {
                $this->payments->markFailed((int) $payment['id'], 'order_cancelled', 'Sipariş iptal edildi.');
            }
        }
    }
}