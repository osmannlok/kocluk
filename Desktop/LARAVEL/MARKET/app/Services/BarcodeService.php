<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BarcodeService
{
    /**
     * Barkodla aktif ürünü bulur. Pasif veya silinmiş ürünler döndürülmez.
     */
    public function findProductByBarcode(string $barcode): ?Product
    {
        $barcode = $this->normalize($barcode);

        if ($barcode === '') {
            return null;
        }

        return Product::query()
            ->where('is_active', true)
            ->whereHas('barcodes', fn ($query) => $query->where('barcode', $barcode))
            ->with('barcodes')
            ->first();
    }

    /**
     * Ürüne yeni barkod ekler. Ürünün ilk barkodu otomatik ana barkod olur.
     */
    public function addBarcode(Product $product, string $barcode, bool $isPrimary = false): ProductBarcode
    {
        $barcode = $this->normalize($barcode);
        $this->assertValid($barcode);

        return DB::transaction(function () use ($product, $barcode, $isPrimary) {
            $this->assertUnique($barcode);

            $hasBarcodes = $product->barcodes()->exists();
            $shouldBePrimary = $isPrimary || ! $hasBarcodes;

            if ($shouldBePrimary) {
                $product->barcodes()->update(['is_primary' => false]);
            }

            return $product->barcodes()->create([
                'barcode' => $barcode,
                'is_primary' => $shouldBePrimary,
            ]);
        });
    }

    /**
     * Mevcut barkodu düzenler. İstenirse ana barkod olarak da işaretler.
     */
    public function updateBarcode(ProductBarcode $productBarcode, string $barcode, ?bool $isPrimary = null): ProductBarcode
    {
        $barcode = $this->normalize($barcode);
        $this->assertValid($barcode);

        return DB::transaction(function () use ($productBarcode, $barcode, $isPrimary) {
            $this->assertUnique($barcode, $productBarcode->id);

            if ($isPrimary === true) {
                ProductBarcode::query()
                    ->where('product_id', $productBarcode->product_id)
                    ->where('id', '!=', $productBarcode->id)
                    ->update(['is_primary' => false]);
            }

            $productBarcode->barcode = $barcode;

            if ($isPrimary !== null) {
                $productBarcode->is_primary = $isPrimary;
            }

            $productBarcode->save();

            $this->ensurePrimaryExists($productBarcode->product_id);

            return $productBarcode->refresh();
        });
    }

    /**
     * Barkodu ana barkod yapar.
     */
    public function setPrimary(ProductBarcode $productBarcode): ProductBarcode
    {
        return DB::transaction(function () use ($productBarcode) {
            ProductBarcode::query()
                ->where('product_id', $productBarcode->product_id)
                ->update(['is_primary' => false]);

            $productBarcode->update(['is_primary' => true]);

            return $productBarcode->refresh();
        });
    }

    /**
     * Barkodu siler. Silinen ana barkodsa kalan ilk barkod ana barkod olur.
     */
    public function deleteBarcode(ProductBarcode $productBarcode): void
    {
        DB::transaction(function () use ($productBarcode) {
            $productId = $productBarcode->product_id;

            $productBarcode->delete();

            $this->ensurePrimaryExists($productId);
        });
    }

    /**
     * Barkod metnini temizler (boşlukları ve satır sonlarını kırpar).
     */
    private function normalize(string $barcode): string
    {
        return trim($barcode);
    }

    /**
     * Barkod boş olamaz ve en fazla 100 karakter olabilir.
     */
    private function assertValid(string $barcode): void
    {
        if ($barcode === '') {
            throw ValidationException::withMessages([
                'barcode' => 'Barkod alanı zorunludur.',
            ]);
        }

        if (mb_strlen($barcode) > 100) {
            throw ValidationException::withMessages([
                'barcode' => 'Barkod en fazla 100 karakter olabilir.',
            ]);
        }
    }

    /**
     * Aynı barkodun ikinci kez eklenmesini engeller.
     */
    private function assertUnique(string $barcode, ?int $ignoreId = null): void
    {
        $exists = ProductBarcode::query()
            ->where('barcode', $barcode)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'barcode' => 'Bu barkod daha önce kaydedilmiş.',
            ]);
        }
    }

    /**
     * Ürünün barkodu varsa mutlaka bir ana barkodu olmasını sağlar.
     */
    private function ensurePrimaryExists(int $productId): void
    {
        $hasPrimary = ProductBarcode::query()
            ->where('product_id', $productId)
            ->where('is_primary', true)
            ->exists();

        if ($hasPrimary) {
            return;
        }

        $first = ProductBarcode::query()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->first();

        $first?->update(['is_primary' => true]);
    }
}