<?php // app/Services/StockService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use DomainException;
use InvalidArgumentException;

/**
 * Stok rezervasyonu, iadesi ve elle düzeltme. Tüm hareketler stock_movements'a yazılır.
 */
final class StockService
{
    private const ADJUST_TYPES = ['manual_adjust', 'correction', 'purchase'];

    private ProductVariant $variants;
    private StockMovement $movements;

    public function __construct()
    {
        $this->variants = new ProductVariant();
        $this->movements = new StockMovement();
    }

    /**
     * Stok düşer. ÇAĞIRAN transaction içinde olmalıdır (lockManyForUpdate zorunlu kılar).
     *
     * @param array<int,int> $qtyByVariantId variant_id => adet
     */
    public function reserve(array $qtyByVariantId, string $refType, int $refId, ?int $userId): void
    {
        $qty = $this->normalize($qtyByVariantId);
        if ($qty === []) {
            return;
        }

        // Kilitleme id sırasıyla yapılır: aynı anda iki sipariş birbirini kilitlemez (deadlock önlemi).
        $locked = $this->variants->lockManyForUpdate(array_keys($qty));

        foreach ($qty as $variantId => $q) {
            $row = $locked[$variantId] ?? null;
            if ($row === null) {
                throw new InsufficientStockException($variantId, 'Ürün artık satışta değil.');
            }
            if ((int) $row['track_stock'] === 0) {
                continue;
            }
            $before = (int) $row['stock_quantity'];
            // Atomik UPDATE ... stock_quantity >= qty: eksiye düşmeyi veritabanı seviyesinde engeller.
            if ($before < $q || !$this->variants->decrementStock($variantId, $q)) {
                throw new InsufficientStockException($variantId);
            }
            $this->movements->record($variantId, $userId, 'sale', -$q, $before - $q, $refType, $refId, null);
        }
    }

    /**
     * Stok iadesi (iptal). ÇAĞIRAN transaction içinde olmalıdır.
     *
     * @param array<int,int> $qtyByVariantId variant_id => adet
     */
    public function release(array $qtyByVariantId, string $refType, int $refId, ?int $userId): void
    {
        $qty = $this->normalize($qtyByVariantId);
        if ($qty === []) {
            return;
        }

        $locked = $this->variants->lockManyForUpdate(array_keys($qty));

        foreach ($qty as $variantId => $q) {
            $row = $locked[$variantId] ?? null;
            if ($row === null) {
                Logger::warning('Stok iadesi atlandı: varyant bulunamadı.', ['variant_id' => $variantId, 'ref_id' => $refId]);
                continue;
            }
            if ((int) $row['track_stock'] === 0) {
                continue;
            }
            $before = (int) $row['stock_quantity'];
            $this->variants->incrementStock($variantId, $q);
            $this->movements->record($variantId, $userId, 'cancel_return', $q, $before + $q, $refType, $refId, null);
        }
    }

    /**
     * Yönetici elle stok düzeltmesi. Açıklama zorunlu, stok eksiye düşemez, denetim kaydı yazılır.
     */
    public function adjust(int $variantId, int $delta, string $type, string $note, int $adminId): void
    {
        if (!in_array($type, self::ADJUST_TYPES, true)) {
            throw new InvalidArgumentException('Geçersiz stok hareket türü.');
        }
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('Stok değişikliği için açıklama zorunludur.');
        }
        if (mb_strlen($note) > 255) {
            throw new InvalidArgumentException('Açıklama en fazla 255 karakter olabilir.');
        }
        if ($delta === 0) {
            throw new InvalidArgumentException('Değişim miktarı sıfır olamaz.');
        }

        Database::instance()->transaction(function () use ($variantId, $delta, $type, $note, $adminId): void {
            $locked = $this->variants->lockManyForUpdate([$variantId]);
            $row = $locked[$variantId] ?? null;
            if ($row === null) {
                throw new DomainException('Varyant bulunamadı.');
            }
            if ((int) $row['track_stock'] === 0) {
                throw new DomainException('Bu varyantta stok takibi kapalı.');
            }

            $before = (int) $row['stock_quantity'];
            $after = $before + $delta;
            if ($after < 0) {
                throw new DomainException('Stok eksiye düşürülemez.');
            }

            if ($delta > 0) {
                $this->variants->incrementStock($variantId, $delta);
            } elseif (!$this->variants->decrementStock($variantId, -$delta)) {
                throw new DomainException('Stok eksiye düşürülemez.');
            }

            $this->movements->record($variantId, $adminId, $type, $delta, $after, 'manual', null, $note);

            AuditService::log(
                'stock.adjust',
                'product_variant',
                $variantId,
                ['stock_quantity' => $before],
                ['stock_quantity' => $after, 'type' => $type, 'note' => $note]
            );
        });
    }

    /**
     * @param array<int|string,mixed> $map
     * @return array<int,int> id sırasına dizilmiş
     */
    private function normalize(array $map): array
    {
        $out = [];
        foreach ($map as $variantId => $q) {
            $variantId = (int) $variantId;
            $q = (int) $q;
            if ($variantId <= 0 || $q <= 0) {
                throw new InvalidArgumentException('Geçersiz stok miktarı.');
            }
            $out[$variantId] = ($out[$variantId] ?? 0) + $q;
        }
        ksort($out);

        return $out;
    }
}