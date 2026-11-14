<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductService
{
    private const IMAGE_DISK = 'public';
    private const IMAGE_DIR  = 'products';

    /**
     * Ürünleri arama, kategori/durum/kritik stok filtresi ve sayfalama ile listeler.
     * Arama: ürün adı, SKU ve barkod.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category:id,name', 'primaryBarcode'])
            ->search($filters['search'] ?? null)
            ->when(filled($filters['category_id'] ?? null), function ($query) use ($filters) {
                $query->where('category_id', $filters['category_id']);
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'passive', fn ($query) => $query->where('is_active', false))
            ->when(! empty($filters['low_stock']), fn ($query) => $query->lowStock())
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Yeni ürün oluşturur.
     * - Ana barkodu product_barcodes tablosuna yazar.
     * - Başlangıç stoğu varsa stock_movements tablosuna "initial" kaydı ekler.
     */
    public function create(array $data): Product
    {
        $imagePath = null;

        try {
            return DB::transaction(function () use ($data, &$imagePath) {
                $barcode      = $data['barcode'] ?? null;
                $initialStock = (string) ($data['stock'] ?? '0');
                $image        = $data['image'] ?? null;

                unset($data['barcode'], $data['image'], $data['remove_image']);

                $data['slug']  = $this->makeUniqueSlug($data['slug'] ?? null, $data['name']);
                $data['stock'] = $initialStock;

                if ($image instanceof UploadedFile) {
                    $imagePath     = $this->storeImage($image);
                    $data['image'] = $imagePath;
                }

                $product = Product::create($data);

                if (filled($barcode)) {
                    $product->barcodes()->create([
                        'barcode'    => $barcode,
                        'is_primary' => true,
                    ]);
                }

                if (bccomp($initialStock, '0', 3) > 0) {
                    StockMovement::create([
                        'product_id'    => $product->id,
                        'user_id'       => Auth::id(),
                        'movement_type' => 'initial',
                        'quantity'      => $initialStock,
                        'stock_before'  => '0',
                        'stock_after'   => $initialStock,
                        'note'          => 'Başlangıç stoğu',
                    ]);
                }

                return $product;
            });
        } catch (Throwable $e) {
            // İşlem başarısız olursa yüklenen görseli temizle.
            $this->deleteImage($imagePath);

            throw $e;
        }
    }

    /**
     * Ürünü günceller. Stok bu metotla değiştirilmez;
     * stok değişimleri StockService üzerinden stock_movements'a yazılır.
     */
    public function update(Product $product, array $data): Product
    {
        $newImagePath = null;
        $oldImagePath = $product->image;
        $removeImage  = ! empty($data['remove_image']);

        try {
            $product = DB::transaction(function () use ($product, $data, $removeImage, &$newImagePath) {
                $barcode = $data['barcode'] ?? null;
                $image   = $data['image'] ?? null;

                unset($data['barcode'], $data['image'], $data['remove_image'], $data['stock']);

                $data['slug'] = $this->makeUniqueSlug($data['slug'] ?? null, $data['name'], $product->id);

                if ($image instanceof UploadedFile) {
                    $newImagePath  = $this->storeImage($image);
                    $data['image'] = $newImagePath;
                } elseif ($removeImage) {
                    $data['image'] = null;
                }

                $product->update($data);

                if (filled($barcode)) {
                    $this->syncPrimaryBarcode($product, $barcode);
                }

                return $product->refresh();
            });
        } catch (Throwable $e) {
            $this->deleteImage($newImagePath);

            throw $e;
        }

        // Transaction başarılı olduktan sonra eski görseli sil.
        if ($oldImagePath && ($newImagePath || $removeImage)) {
            $this->deleteImage($oldImagePath);
        }

        return $product;
    }

    /**
     * Ürünü pasife alır veya yeniden aktif eder.
     * Pasif ürün satışta seçilemez; geçmiş satışlar etkilenmez.
     */
    public function toggleActive(Product $product): Product
    {
        $product->update(['is_active' => ! $product->is_active]);

        return $product->refresh();
    }

    /**
     * Ürünü soft delete ile siler. Görsel, barkod ve geçmiş kayıtlar korunur.
     */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->delete();
        });
    }

    /**
     * Ana barkodu günceller veya oluşturur.
     * Barkod ürünün başka bir barkoduysa onu ana barkod yapar.
     */
    private function syncPrimaryBarcode(Product $product, string $barcode): void
    {
        $existing = $product->barcodes()->where('barcode', $barcode)->first();

        $product->barcodes()->where('is_primary', true)->update(['is_primary' => false]);

        if ($existing) {
            $existing->update(['is_primary' => true]);

            return;
        }

        ProductBarcode::create([
            'product_id' => $product->id,
            'barcode'    => $barcode,
            'is_primary' => true,
        ]);
    }

    /**
     * Silinmiş kayıtlar dahil benzersiz slug üretir.
     */
    private function makeUniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = filled($slug) ? Str::slug($slug) : Str::slug($name);
        $base = $base !== '' ? Str::limit($base, 200, '') : 'urun';

        $candidate = $base;
        $counter   = 2;

        while (
            Product::withTrashed()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function storeImage(UploadedFile $file): string
    {
        return $file->store(self::IMAGE_DIR, self::IMAGE_DISK);
    }

    private function deleteImage(?string $path): void
    {
        if ($path && Storage::disk(self::IMAGE_DISK)->exists($path)) {
            Storage::disk(self::IMAGE_DISK)->delete($path);
        }
    }
}