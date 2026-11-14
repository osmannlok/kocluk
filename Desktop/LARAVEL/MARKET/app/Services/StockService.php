<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_SALE = 'sale';
    public const TYPE_RETURN = 'return';
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_CORRECTION = 'correction';
    public const TYPE_INITIAL = 'initial';

    /**
     * Ürün girişi (tedarikçiden alım). Stoğu artırır.
     */
    public function addPurchase(Product|int $product, float $quantity, ?int $userId = null, ?int $purchaseId = null, ?string $note = null): StockMovement
    {
        return $this->increase($product, $quantity, self::TYPE_PURCHASE, $userId, $note, purchaseId: $purchaseId);
    }

    /**
     * Başlangıç stok girişi. Stoğu artırır.
     */
    public function addInitial(Product|int $product, float $quantity, ?int $userId = null, ?string $note = null): StockMovement
    {
        return $this->increase($product, $quantity, self::TYPE_INITIAL, $userId, $note);
    }

    /**
     * Satış çıkışı. Stoğu azaltır; stok yetersizse hata verir.
     */
    public function removeForSale(Product|int $product, float $quantity, int $saleId, ?int $userId = null, ?string $note = null): StockMovement
    {
        return $this->decrease($product, $quantity, self::TYPE_SALE, $userId, $note, saleId: $saleId);
    }

    /**
     * İade veya satış iptali girişi. Stoğu artırır.
     */
    public function addReturn(Product|int $product, float $quantity, ?int $saleId = null, ?int $userId = null, ?string $note = null): StockMovement
    {
        return $this->increase($product, $quantity, self::TYPE_RETURN, $userId, $note, saleId: $saleId);
    }

    /**
     * Fire / bozulma kaydı. Stoğu azaltır; gerekçe zorunludur.
     */
    public function recordDamage(Product|int $product, float $quantity, string $note, ?int $userId = null): StockMovement
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => 'Fire gerekçesi zorunludur.',
            ]);
        }

        return $this->decrease($product, $quantity, self::TYPE_DAMAGE, $userId, $note);
    }

    /**
     * Stok sayımı. Gerçek stok girilir, fark varsa correction kaydı oluşur.
     * Fark yoksa null döner. Kayıttaki quantity işaretli farktır (+ fazla, - eksik).
     */
    public function recordCount(Product|int $product, float $countedStock, string $note, ?int $userId = null): ?StockMovement
    {
        if ($countedStock < 0) {
            throw ValidationException::withMessages([
                'counted_stock' => 'Sayılan stok eksi olamaz.',
            ]);
        }

        if (trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => 'Sayım farkı gerekçesi zorunludur.',
            ]);
        }

        return DB::transaction(function () use ($product, $countedStock, $note, $userId) {
            $locked = $this->lockProduct($product);

            $before = (float) $locked->stock;
            $after = round($countedStock, 3);
            $difference = round($after - $before, 3);

            if ($difference === 0.0) {
                return null;
            }

            return $this->persist($locked, self::TYPE_CORRECTION, $difference, $before, $after, $userId, $note);
        });
    }

    /**
     * Kritik stok seviyesinin altındaki aktif ürünler.
     */
    public function getLowStockProducts(): Collection
    {
        return Product::query()
            ->lowStock()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Ürün kritik stok seviyesinde veya altında mı?
     */
    public function isLowStock(Product $product): bool
    {
        return (float) $product->stock <= (float) $product->critical_stock;
    }

    private function increase(Product|int $product, float $quantity, string $type, ?int $userId, ?string $note, ?int $purchaseId = null, ?int $saleId = null): StockMovement
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($product, $quantity, $type, $userId, $note, $purchaseId, $saleId) {
            $locked = $this->lockProduct($product);

            $before = (float) $locked->stock;
            $after = round($before + $quantity, 3);

            return $this->persist($locked, $type, round($quantity, 3), $before, $after, $userId, $note, $purchaseId, $saleId);
        });
    }

    private function decrease(Product|int $product, float $quantity, string $type, ?int $userId, ?string $note, ?int $purchaseId = null, ?int $saleId = null): StockMovement
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($product, $quantity, $type, $userId, $note, $purchaseId, $saleId) {
            $locked = $this->lockProduct($product);

            $before = (float) $locked->stock;
            $after = round($before - $quantity, 3);

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Yetersiz stok: {$locked->name} için mevcut stok {$before}, istenen {$quantity}.",
                ]);
            }

            return $this->persist($locked, $type, round($quantity, 3), $before, $after, $userId, $note, $purchaseId, $saleId);
        });
    }

    /**
     * Ürün satırını kilitleyerek okur; aynı anda iki işlem stoğu bozamaz.
     */
    private function lockProduct(Product|int $product): Product
    {
        $id = $product instanceof Product ? $product->id : $product;

        return Product::query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * Ürün stoğunu günceller ve stock_movements kaydını oluşturur.
     */
    private function persist(Product $product, string $type, float $quantity, float $before, float $after, ?int $userId, ?string $note, ?int $purchaseId = null, ?int $saleId = null): StockMovement
    {
        $product->stock = $after;
        $product->save();

        return StockMovement::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'purchase_id' => $purchaseId,
            'sale_id' => $saleId,
            'movement_type' => $type,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $after,
            'note' => $note,
        ]);
    }

    private function assertPositive(float $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Miktar sıfırdan büyük olmalıdır.',
            ]);
        }
    }
}