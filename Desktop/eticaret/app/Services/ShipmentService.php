<?php
// app/Services/ShipmentService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\ValidationException;
use App\Models\Order;
use App\Models\Shipment;
use DomainException;
use Throwable;

/**
 * Kargo kayıtları iş mantığı: kargoya verme, düzeltme, teslim edildi işaretleme.
 * Takip bağlantısı yalnızca sunucuda config/carriers.php şablonundan üretilir.
 */
final class ShipmentService
{
    private const TRACKING_PATTERN = '/^[A-Za-z0-9-]{6,40}$/';

    private Database $db;
    private Order $orders;
    private Shipment $shipments;
    private OrderStatusService $statusService;
    private OrderNotificationService $notifier;

    public function __construct(
        ?OrderStatusService $statusService = null,
        ?OrderNotificationService $notifier = null
    ) {
        $this->db = Database::instance();
        $this->orders = new Order();
        $this->shipments = new Shipment();
        $this->statusService = $statusService ?? new OrderStatusService();
        $this->notifier = $notifier ?? new OrderNotificationService();
    }

    /**
     * Siparişi kargoya verir; kargo kaydı + durum geçişi tek transaction'dadır.
     */
    public function addShipment(int $orderId, string $carrierKey, string $trackingNumber, ?string $note, int $adminId): int
    {
        $carrier = $this->resolveCarrier($carrierKey);
        $tracking = $this->normalizeTracking($trackingNumber);
        $noteValue = $this->normalizeNote($note);
        $trackingUrl = $this->buildTrackingUrl($carrier['tracking_url'], $tracking);

        $shipmentId = $this->db->transaction(function () use ($orderId, $carrier, $tracking, $trackingUrl, $noteValue, $adminId): int {
            // Eşzamanlı çift kargolamayı önlemek için sipariş satırı kilitlenir.
            $order = $this->orders->findByIdForUpdate($orderId);
            if ($order === null) {
                throw new HttpException(404, 'Sipariş bulunamadı.');
            }
            if ((string) $order['status'] !== 'preparing') {
                throw new DomainException('Sipariş kargoya verilebilir durumda değil');
            }

            $data = [
                'order_id'        => $orderId,
                'carrier'         => $carrier['name'],
                'tracking_number' => $tracking,
                'tracking_url'    => $trackingUrl,
                'shipped_at'      => now(),
                'note'            => $noteValue,
                'created_by'      => $adminId,
            ];
            $id = $this->shipments->createShipment($data);

            $this->statusService->transition($orderId, 'shipped', $adminId, 'Kargoya verildi');

            AuditService::log('shipment.create', 'shipment', $id, null, [
                'order_id'        => $orderId,
                'order_number'    => $order['order_number'] ?? null,
                'carrier'         => $carrier['name'],
                'tracking_number' => $tracking,
                'tracking_url'    => $trackingUrl,
                'note'            => $noteValue,
            ]);

            return $id;
        });

        // E-posta transaction dışında; hata akışı bozmaz.
        try {
            $this->notifier->sendShipped($orderId, (int) $shipmentId);
        } catch (Throwable $e) {
            Logger::warning('Kargo e-postası gönderilemedi', [
                'order_id'    => $orderId,
                'shipment_id' => $shipmentId,
                'error'       => $e->getMessage(),
            ]);
        }

        return (int) $shipmentId;
    }

    /**
     * Mevcut kargo kaydını düzeltir (firma, takip no, not). URL yeniden üretilir.
     */
    public function updateShipment(int $shipmentId, string $carrierKey, string $trackingNumber, ?string $note, int $adminId): bool
    {
        $carrier = $this->resolveCarrier($carrierKey);
        $tracking = $this->normalizeTracking($trackingNumber);
        $noteValue = $this->normalizeNote($note);
        $trackingUrl = $this->buildTrackingUrl($carrier['tracking_url'], $tracking);

        return (bool) $this->db->transaction(function () use ($shipmentId, $carrier, $tracking, $trackingUrl, $noteValue, $adminId): bool {
            $shipment = $this->shipments->find($shipmentId);
            if ($shipment === null) {
                throw new HttpException(404, 'Kargo kaydı bulunamadı.');
            }

            // Sipariş kilitlenir; yalnızca kargodaki/teslim edilmiş siparişin kaydı düzeltilebilir.
            $order = $this->orders->findByIdForUpdate((int) $shipment['order_id']);
            if ($order === null) {
                throw new HttpException(404, 'Sipariş bulunamadı.');
            }
            if (!in_array((string) $order['status'], ['shipped', 'delivered'], true)) {
                throw new DomainException('Bu siparişin kargo kaydı düzenlenemez');
            }

            $old = [
                'carrier'         => $shipment['carrier'] ?? null,
                'tracking_number' => $shipment['tracking_number'] ?? null,
                'tracking_url'    => $shipment['tracking_url'] ?? null,
                'note'            => $shipment['note'] ?? null,
            ];
            $new = [
                'carrier'         => $carrier['name'],
                'tracking_number' => $tracking,
                'tracking_url'    => $trackingUrl,
                'note'            => $noteValue,
            ];

            $ok = $this->shipments->updateShipment($shipmentId, $new);

            AuditService::log('shipment.update', 'shipment', $shipmentId, $old, $new);

            return $ok;
        });
    }

    /**
     * Kargodaki siparişi teslim edildi olarak işaretler.
     */
    public function markDelivered(int $orderId, int $adminId): void
    {
        $this->db->transaction(function () use ($orderId, $adminId): void {
            $order = $this->orders->findByIdForUpdate($orderId);
            if ($order === null) {
                throw new HttpException(404, 'Sipariş bulunamadı.');
            }
            if ((string) $order['status'] !== 'shipped') {
                throw new DomainException('Sipariş teslim edildi olarak işaretlenemez');
            }

            $this->statusService->transition($orderId, 'delivered', $adminId, 'Teslim edildi');

            foreach ($this->shipments->forOrder($orderId) as $shipment) {
                if (($shipment['delivered_at'] ?? null) === null) {
                    $this->shipments->markDelivered((int) $shipment['id']);
                }
            }

            AuditService::log('shipment.delivered', 'order', $orderId, ['status' => 'shipped'], [
                'status'       => 'delivered',
                'order_number' => $order['order_number'] ?? null,
            ]);
        });
    }

    /**
     * Firma kodunu config/carriers.php içinde doğrular.
     *
     * @return array{name:string,tracking_url:?string}
     */
    private function resolveCarrier(string $carrierKey): array
    {
        $carriers = config('carriers', []);
        if (!is_array($carriers) || !isset($carriers[$carrierKey]) || !is_array($carriers[$carrierKey])) {
            throw new ValidationException(['carrier' => 'Geçersiz kargo firması.']);
        }

        $item = $carriers[$carrierKey];
        $name = (string) ($item['name'] ?? '');
        if ($name === '') {
            throw new ValidationException(['carrier' => 'Geçersiz kargo firması.']);
        }

        $template = $item['tracking_url'] ?? null;

        return [
            'name'         => $name,
            'tracking_url' => is_string($template) && $template !== '' ? $template : null,
        ];
    }

    private function normalizeTracking(string $trackingNumber): string
    {
        $tracking = trim($trackingNumber);
        if (preg_match(self::TRACKING_PATTERN, $tracking) !== 1) {
            throw new ValidationException(
                ['tracking_number' => 'Takip numarası 6-40 karakter olmalı; yalnızca harf, rakam ve tire içerebilir.']
            );
        }

        return $tracking;
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }
        $note = trim($note);
        if ($note === '') {
            return null;
        }
        if (mb_strlen($note) > 255) {
            throw new ValidationException(['note' => 'Not en fazla 255 karakter olabilir.']);
        }

        return $note;
    }

    /**
     * Yöneticiden URL alınmaz; yalnızca config şablonundan, rawurlencode ile üretilir.
     * Sonuç https:// ile başlamıyorsa null döner (güvensiz şema/açık yönlendirme önlemi).
     */
    private function buildTrackingUrl(?string $template, string $tracking): ?string
    {
        if ($template === null || !str_contains($template, '{tracking}')) {
            return null;
        }

        $url = str_replace('{tracking}', rawurlencode($tracking), $template);
        if (!str_starts_with($url, 'https://') || mb_strlen($url) > 255) {
            return null;
        }

        return $url;
    }
}