<?php
// app/Services/ProductAdminService.php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use DateTimeImmutable;
use DomainException;
use PDOException;

/**
 * Yönetim tarafı ürün + varyant kaydetme servisi.
 * Güvenlik/bütünlük: stok miktarı ürün formundan doğrudan yazılmaz; yalnızca
 * StockService::adjust ile değişir, böylece her değişim stock_movements'a düşer.
 * Yetki kontrolü çağıran controller'dadır (product.create / product.update / product.delete).
 */
final class ProductAdminService
{
    /** Ürün metin alanları => azami uzunluk (karakter). */
    private const TEXT_FIELDS = [
        'short_description'  => 500,
        'description'        => 60000,
        'origin_text'        => 190,
        'producer_name'      => 190,
        'ingredients'        => 20000,
        'allergen_info'      => 20000,
        'storage_conditions' => 255,
        'shelf_life_info'    => 190,
        'meta_title'         => 160,
        'meta_description'   => 255,
    ];

    /** Denetim kaydında karşılaştırılacak ürün alanları. */
    private const AUDIT_FIELDS = [
        'category_id', 'region_id', 'name', 'slug', 'short_description', 'description',
        'origin_text', 'producer_name', 'ingredients', 'allergen_info', 'storage_conditions',
        'shelf_life_info', 'tax_rate', 'is_active', 'is_featured', 'meta_title', 'meta_description',
    ];

    private Product $products;
    private ProductVariant $variants;
    private Category $categories;
    private Region $regions;
    private StockService $stock;

    public function __construct(
        ?Product $products = null,
        ?ProductVariant $variants = null,
        ?Category $categories = null,
        ?Region $regions = null,
        ?StockService $stock = null
    ) {
        $this->products = $products ?? new Product();
        $this->variants = $variants ?? new ProductVariant();
        $this->categories = $categories ?? new Category();
        $this->regions = $regions ?? new Region();
        $this->stock = $stock ?? new StockService();
    }

    /**
     * Ürünü ve varyantlarını kaydeder (productId null ise yeni ürün).
     *
     * @param array<string,mixed> $data
     * @param array<int,array<string,mixed>> $variants
     */
    public function save(?int $productId, array $data, array $variants, ?int $adminId): int
    {
        $existing = null;
        if ($productId !== null) {
            $existing = $this->products->find($productId);
            if ($existing === null) {
                throw new HttpException(404, 'Ürün bulunamadı.');
            }
        }

        $existingVariants = [];
        if ($productId !== null) {
            foreach ($this->variants->forProduct($productId, false) as $v) {
                $existingVariants[(int) $v['id']] = $v;
            }
        }

        $errors = [];
        $row = $this->normalizeProduct($data, $productId, $errors);
        $rows = $this->normalizeVariants($variants, $existingVariants, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors, $data);
        }

        $pending = [];

        try {
            $savedId = Database::instance()->transaction(
                function () use ($productId, $existing, $row, $rows, $existingVariants, $adminId, &$pending): int {
                    $row['updated_by'] = $adminId;
                    if ($productId === null) {
                        $row['created_by'] = $adminId;
                        $pid = $this->products->create($row);
                    } else {
                        $this->products->update($productId, $row);
                        $pid = $productId;
                    }

                    // Mevcut varyant satırları kilitlenir: stok farkı güncel değere göre hesaplanır.
                    $locked = $existingVariants === []
                        ? []
                        : $this->variants->lockManyForUpdate(array_keys($existingVariants));

                    $submittedIds = [];
                    foreach ($rows as $r) {
                        $payload = [
                            'name'                => $r['name'],
                            'sku'                 => $r['sku'],
                            'barcode'             => $r['barcode'],
                            'weight_grams'        => $r['weight_grams'],
                            'price'               => $r['price'],
                            'sale_price'          => $r['sale_price'],
                            'sale_starts_at'      => $r['sale_starts_at'],
                            'sale_ends_at'        => $r['sale_ends_at'],
                            'low_stock_threshold' => $r['low_stock_threshold'],
                            'track_stock'         => $r['track_stock'],
                            'is_default'          => $r['is_default'],
                            'is_active'           => $r['is_active'],
                            'sort_order'          => $r['sort_order'],
                        ];

                        if ($r['id'] !== null) {
                            $vid = $r['id'];
                            $this->variants->update($vid, $payload);
                            $current = (int) ($locked[$vid]['stock_quantity'] ?? $existingVariants[$vid]['stock_quantity'] ?? 0);
                            $delta = $r['stock_quantity'] - $current;
                            if ($r['track_stock'] === 1 && $delta !== 0) {
                                $pending[] = [$vid, $delta, 'manual_adjust', 'Ürün formundan stok düzeltmesi'];
                            }
                        } else {
                            // Yeni varyant 0 stokla açılır; başlangıç stoğu 'initial' hareketiyle girilir.
                            $vid = $this->variants->create($payload + ['product_id' => $pid, 'stock_quantity' => 0]);
                            if ($r['track_stock'] === 1 && $r['stock_quantity'] > 0) {
                                $pending[] = [$vid, $r['stock_quantity'], 'initial', 'Başlangıç stoğu (ürün oluşturma)'];
                            }
                        }
                        $submittedIds[] = $vid;
                    }

                    // Formda olmayan mevcut varyantlar silinmez (sipariş geçmişi bağlı): pasifleştirilir.
                    foreach ($existingVariants as $vid => $v) {
                        if (!in_array($vid, $submittedIds, true) && ((int) $v['is_active'] === 1 || (int) $v['is_default'] === 1)) {
                            $this->variants->update($vid, ['is_active' => 0, 'is_default' => 0]);
                        }
                    }

                    // Denetim: yalnızca değişen alanlar + varyant özeti (eski/yeni).
                    $newSnapshot = $this->variantSnapshotFromRows($rows);
                    if ($existing === null) {
                        $this->audit($adminId, 'product.create', $pid, null, $this->pick($row, self::AUDIT_FIELDS) + ['variants' => $newSnapshot]);
                    } else {
                        [$oldDiff, $newDiff] = $this->diff($existing, $row, self::AUDIT_FIELDS);
                        $oldVariants = $this->variantSnapshotFromExisting($existingVariants);
                        if ($oldVariants !== $newSnapshot) {
                            $oldDiff['variants'] = $oldVariants;
                            $newDiff['variants'] = $newSnapshot;
                        }
                        $this->audit($adminId, 'product.update', $pid, $oldDiff, $newDiff);
                    }

                    return $pid;
                }
            );
        } catch (PDOException $e) {
            // Eşzamanlı kayıtlarda UNIQUE ihlali kullanıcıya doğrulama hatası olarak döner.
            if ((string) $e->getCode() === '23000' && stripos($e->getMessage(), 'Duplicate') !== false) {
                $key = stripos($e->getMessage(), 'sku') !== false ? 'variants' : 'slug';
                $msg = $key === 'slug' ? 'Bu slug başka bir üründe kullanılıyor.' : 'SKU başka bir varyantta kullanılıyor.';
                throw new ValidationException([$key => $msg], $data);
            }
            throw $e;
        }

        // SAPMA: StockService::adjust kendi transaction'ını açar; iç içe transaction çakışmasın
        // diye stok hareketleri ürün kaydı commit edildikten SONRA, sırayla uygulanır.
        foreach ($pending as [$vid, $delta, $type, $note]) {
            if ($adminId === null) {
                throw new DomainException('Ürün kaydedildi ancak stok değişimi için yönetici kimliği gerekli.');
            }
            try {
                $this->stock->adjust($vid, $delta, $type, $note, $adminId);
            } catch (\InvalidArgumentException | DomainException $e) {
                throw new DomainException('Ürün kaydedildi ancak stok güncellenemedi: ' . $e->getMessage(), 0, $e);
            }
        }

        return $savedId;
    }

    /** Ürünü soft delete yapar; önce pasifleştirilir (sepet/vitrin sızıntısı olmasın). */
    public function delete(int $productId, int $adminId): void
    {
        $product = $this->products->find($productId);
        if ($product === null) {
            throw new HttpException(404, 'Ürün bulunamadı.');
        }

        Database::instance()->transaction(function () use ($productId, $adminId, $product): void {
            $this->products->update($productId, ['is_active' => 0, 'updated_by' => $adminId]);
            $this->products->softDelete($productId);
            $this->audit(
                $adminId,
                'product.delete',
                $productId,
                ['name' => $product['name'], 'slug' => $product['slug'], 'is_active' => (int) $product['is_active']],
                ['deleted' => true]
            );
        });
    }

    /** Aktif/pasif durumunu tersine çevirir; yeni durumu döner. */
    public function toggleActive(int $productId, int $adminId): bool
    {
        $product = $this->products->find($productId);
        if ($product === null) {
            throw new HttpException(404, 'Ürün bulunamadı.');
        }

        $new = (int) $product['is_active'] === 1 ? 0 : 1;
        Database::instance()->transaction(function () use ($productId, $adminId, $product, $new): void {
            $this->products->update($productId, ['is_active' => $new, 'updated_by' => $adminId]);
            $this->audit($adminId, 'product.toggle', $productId, ['is_active' => (int) $product['is_active']], ['is_active' => $new]);
        });

        return $new === 1;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $errors
     * @return array<string,mixed>
     */
    private function normalizeProduct(array $data, ?int $productId, array &$errors): array
    {
        $row = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) {
            $errors['name'] = 'Ürün adı zorunludur ve en çok 190 karakter olabilir.';
        }
        $row['name'] = $name;

        $categoryId = $this->toInt($data['category_id'] ?? null);
        if ($categoryId === null || $categoryId < 1 || $this->categories->find($categoryId) === null) {
            $errors['category_id'] = 'Geçerli bir kategori seçin.';
        }
        $row['category_id'] = $categoryId ?? 0;

        $regionRaw = $data['region_id'] ?? null;
        $regionId = null;
        if ($regionRaw !== null && $regionRaw !== '') {
            $regionId = $this->toInt($regionRaw);
            if ($regionId === null || $regionId < 1 || $this->regions->find($regionId) === null) {
                $errors['region_id'] = 'Geçerli bir yöre seçin.';
                $regionId = null;
            }
        }
        $row['region_id'] = $regionId;

        foreach (self::TEXT_FIELDS as $field => $max) {
            $value = trim((string) ($data[$field] ?? ''));
            if (mb_strlen($value) > $max) {
                $errors[$field] = 'Bu alan en çok ' . $max . ' karakter olabilir.';
                $value = '';
            }
            // Metinler düz metin saklanır; çıktıda e() ile kaçırılır (HTML kabul edilmez).
            $row[$field] = $value === '' ? null : $value;
        }

        $taxRate = trim(str_replace(',', '.', (string) ($data['tax_rate'] ?? '10.00')));
        if (preg_match('/^\d{1,3}(\.\d{1,2})?$/', $taxRate) !== 1 || (float) $taxRate > 100.0) {
            $errors['tax_rate'] = 'KDV oranı 0 ile 100 arasında olmalıdır.';
            $taxRate = '10.00';
        }
        $row['tax_rate'] = number_format((float) $taxRate, 2, '.', '');

        $row['is_active'] = $this->toBool($data['is_active'] ?? false) ? 1 : 0;
        $row['is_featured'] = $this->toBool($data['is_featured'] ?? false) ? 1 : 0;

        // Slug: boşsa addan üretilir ve benzersizleştirilir; elle girilmişse çakışma hatadır.
        $slugInput = trim((string) ($data['slug'] ?? ''));
        if ($slugInput === '') {
            $base = slugify($name);
            if ($base === '') {
                $base = 'urun';
            }
            $slug = $base;
            $i = 2;
            while ($this->products->slugExists($slug, $productId) && $i < 100) {
                $slug = $base . '-' . $i;
                $i++;
            }
            if ($this->products->slugExists($slug, $productId)) {
                $errors['slug'] = 'Benzersiz slug üretilemedi; elle girin.';
            }
        } else {
            $slug = slugify($slugInput);
            if ($slug === '' || mb_strlen($slug) > 220) {
                $errors['slug'] = 'Slug geçersiz.';
            } elseif ($this->products->slugExists($slug, $productId)) {
                $errors['slug'] = 'Bu slug başka bir üründe kullanılıyor.';
            }
        }
        $row['slug'] = $slug;

        return $row;
    }

    /**
     * @param array<int,array<string,mixed>> $variants
     * @param array<int,array<string,mixed>> $existingVariants id => satır
     * @param array<string,string> $errors
     * @return list<array<string,mixed>>
     */
    private function normalizeVariants(array $variants, array $existingVariants, array &$errors): array
    {
        $variants = array_values($variants);
        if ($variants === []) {
            $errors['variants'] = 'En az bir varyant eklemelisiniz.';
            return [];
        }
        if (count($variants) > 50) {
            $errors['variants'] = 'Bir üründe en çok 50 varyant olabilir.';
            return [];
        }

        $rows = [];
        $seenSku = [];
        $seenIds = [];

        foreach ($variants as $i => $v) {
            $p = 'variants.' . $i . '.';
            if (!is_array($v)) {
                $errors[$p . 'name'] = 'Varyant satırı geçersiz.';
                continue;
            }

            // IDOR: gönderilen varyant kimliği yalnızca bu ürüne ait olabilir.
            $id = null;
            if (($v['id'] ?? '') !== '' && ($v['id'] ?? null) !== null) {
                $id = $this->toInt($v['id']);
                if ($id === null || !isset($existingVariants[$id]) || isset($seenIds[$id])) {
                    $errors[$p . 'id'] = 'Varyant bu ürüne ait değil.';
                    $id = null;
                } else {
                    $seenIds[$id] = true;
                }
            }

            $name = trim((string) ($v['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                $errors[$p . 'name'] = 'Varyant adı zorunludur (en çok 100 karakter).';
            }

            $sku = trim((string) ($v['sku'] ?? ''));
            if ($sku === '' || mb_strlen($sku) > 64 || preg_match('/^[A-Za-z0-9._-]+$/', $sku) !== 1) {
                $errors[$p . 'sku'] = 'SKU zorunludur; harf, rakam, nokta, tire ve alt çizgi kullanılabilir (en çok 64).';
            } else {
                $skuKey = strtolower($sku);
                if (isset($seenSku[$skuKey])) {
                    $errors[$p . 'sku'] = 'Aynı SKU bu formda birden fazla kez kullanılmış.';
                } elseif ($this->variants->skuExists($sku, $id)) {
                    $errors[$p . 'sku'] = 'Bu SKU başka bir varyantta kullanılıyor.';
                }
                $seenSku[$skuKey] = true;
            }

            $barcode = trim((string) ($v['barcode'] ?? ''));
            if (mb_strlen($barcode) > 64) {
                $errors[$p . 'barcode'] = 'Barkod en çok 64 karakter olabilir.';
                $barcode = '';
            }

            $weight = $this->toInt($v['weight_grams'] ?? null);
            if ($weight === null || $weight < 1 || $weight > 100000000) {
                $errors[$p . 'weight_grams'] = 'Gramaj 1 veya daha büyük bir tam sayı olmalıdır.';
                $weight = 1;
            }

            $price = $this->money($v['price'] ?? null);
            $priceCents = null;
            if ($price === null || ($priceCents = Money::toCents($price)) <= 0) {
                $errors[$p . 'price'] = 'Geçerli bir fiyat girin (0\'dan büyük, en çok 2 ondalık).';
                $price = '0.00';
                $priceCents = null;
            }

            $salePrice = null;
            $saleRaw = $v['sale_price'] ?? null;
            if ($saleRaw !== null && trim((string) $saleRaw) !== '') {
                $salePrice = $this->money($saleRaw);
                if ($salePrice === null || Money::toCents($salePrice) < 0) {
                    $errors[$p . 'sale_price'] = 'İndirimli fiyat geçersiz.';
                    $salePrice = null;
                } elseif ($priceCents !== null && Money::toCents($salePrice) >= $priceCents) {
                    $errors[$p . 'sale_price'] = 'İndirimli fiyat, normal fiyattan düşük olmalıdır.';
                    $salePrice = null;
                }
            }

            $starts = $this->dateTime($v['sale_starts_at'] ?? null, $p . 'sale_starts_at', $errors);
            $ends = $this->dateTime($v['sale_ends_at'] ?? null, $p . 'sale_ends_at', $errors);
            if ($salePrice === null) {
                // İndirimli fiyat yoksa tarih penceresi anlamsızdır.
                $starts = null;
                $ends = null;
            } elseif ($starts !== null && $ends !== null && $starts >= $ends) {
                $errors[$p . 'sale_ends_at'] = 'İndirim bitiş tarihi başlangıçtan sonra olmalıdır.';
            }

            $stock = $this->toInt($v['stock_quantity'] ?? 0);
            if ($stock === null || $stock < 0 || $stock > 1000000) {
                $errors[$p . 'stock_quantity'] = 'Stok 0 veya daha büyük bir tam sayı olmalıdır.';
                $stock = 0;
            }

            $threshold = $this->toInt($v['low_stock_threshold'] ?? 5);
            if ($threshold === null || $threshold < 0 || $threshold > 1000000) {
                $errors[$p . 'low_stock_threshold'] = 'Kritik stok eşiği geçersiz.';
                $threshold = 5;
            }

            $sort = $this->toInt($v['sort_order'] ?? $i);

            $rows[] = [
                'id'                  => $id,
                'name'                => $name,
                'sku'                 => $sku,
                'barcode'             => $barcode === '' ? null : $barcode,
                'weight_grams'        => $weight,
                'price'               => $price,
                'sale_price'          => $salePrice,
                'sale_starts_at'      => $starts,
                'sale_ends_at'        => $ends,
                'stock_quantity'      => $stock,
                'low_stock_threshold' => $threshold,
                'track_stock'         => $this->toBool($v['track_stock'] ?? true) ? 1 : 0,
                'is_default'          => $this->toBool($v['is_default'] ?? false) ? 1 : 0,
                'is_active'           => $this->toBool($v['is_active'] ?? true) ? 1 : 0,
                'sort_order'          => $sort ?? $i,
            ];
        }

        // En az bir aktif varyant ve tam bir varsayılan (aktif) varyant zorunlu.
        $activeIdx = [];
        $defaultIdx = [];
        foreach ($rows as $idx => $r) {
            if ($r['is_active'] === 1) {
                $activeIdx[] = $idx;
            }
            if ($r['is_default'] === 1) {
                $defaultIdx[] = $idx;
            }
        }
        if ($activeIdx === []) {
            $errors['variants'] = 'En az bir aktif varyant olmalıdır.';
        } elseif (count($defaultIdx) > 1) {
            $errors['variants'] = 'Yalnızca bir varyant varsayılan olabilir.';
        } else {
            if ($defaultIdx === [] || $rows[$defaultIdx[0]]['is_active'] !== 1) {
                // Varsayılan seçilmemiş ya da pasif: ilk aktif varyant varsayılan yapılır.
                foreach ($rows as $idx => $r) {
                    $rows[$idx]['is_default'] = 0;
                }
                $rows[$activeIdx[0]]['is_default'] = 1;
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function money(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = str_replace([' ', ','], ['', '.'], trim((string) $value));
        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $s) !== 1) {
            return null;
        }

        // Float kullanılmaz: kuruşa çevirip geri string'e dönülür.
        return Money::fromCents(Money::toCents($s));
    }

    /** @param array<string,string> $errors */
    private function dateTime(mixed $value, string $key, array &$errors): ?string
    {
        $s = trim((string) ($value ?? ''));
        if ($s === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($s))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            $errors[$key] = 'Tarih biçimi geçersiz.';
            return null;
        }
    }

    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) && !is_float($value)) {
            return null;
        }
        $r = filter_var(is_string($value) ? trim($value) : $value, FILTER_VALIDATE_INT);

        return $r === false ? null : (int) $r;
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string,mixed> $source
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    private function pick(array $source, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $source[$k] ?? null;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,mixed> $new
     * @param list<string> $keys
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private function diff(array $old, array $new, array $keys): array
    {
        $o = [];
        $n = [];
        foreach ($keys as $k) {
            $ov = $old[$k] ?? null;
            $nv = $new[$k] ?? null;
            if ((string) $ov !== (string) $nv) {
                $o[$k] = $ov;
                $n[$k] = $nv;
            }
        }

        return [$o, $n];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private function variantSnapshotFromRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['sku']] = [
                'name'       => $r['name'],
                'price'      => $r['price'],
                'sale_price' => $r['sale_price'],
                'stock'      => $r['track_stock'] === 1 ? $r['stock_quantity'] : null,
                'is_active'  => $r['is_active'],
                'is_default' => $r['is_default'],
            ];
        }
        ksort($out);

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $existing
     * @return array<string,array<string,mixed>>
     */
    private function variantSnapshotFromExisting(array $existing): array
    {
        $out = [];
        foreach ($existing as $v) {
            $out[(string) $v['sku']] = [
                'name'       => $v['name'],
                'price'      => (string) $v['price'],
                'sale_price' => $v['sale_price'] === null ? null : (string) $v['sale_price'],
                'stock'      => (int) $v['track_stock'] === 1 ? (int) $v['stock_quantity'] : null,
                'is_active'  => (int) $v['is_active'],
                'is_default' => (int) $v['is_default'],
            ];
        }
        ksort($out);

        return $out;
    }

    /**
     * Yönetici işlemini audit_logs'a yazar (kim, ne, eski/yeni, IP).
     *
     * @param array<string,mixed>|null $old
     * @param array<string,mixed>|null $new
     */
    private function audit(?int $adminId, string $action, int $entityId, ?array $old, ?array $new): void
    {
        $request = Request::capture();
        (new AuditLog())->write(
            $adminId,
            $action,
            'product',
            $entityId,
            $old,
            $new,
            ip_to_bin($request->ip()),
            $request->userAgent()
        );
    }
}