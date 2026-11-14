<?php // app/Services/CatalogService.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;
use App\Models\Category;
use App\Models\Product;
use InvalidArgumentException;

/**
 * Vitrin katalog iş mantığı: liste filtreleme/sıralama normalizasyonu,
 * ürün detayı hazırlığı ve arama önerileri. SQL yok; yalnızca modeller çağrılır.
 */
final class CatalogService
{
    private const SORTS = ['newest', 'price_asc', 'price_desc', 'name'];
    private const PER_PAGE = 12;
    private const MAX_PAGE = 10000;
    private const MAX_PRICE_CENTS = 10000000; // 100.000,00 TL
    private const MIN_QUERY = 2;
    private const MAX_QUERY = 80;
    private const RELATED_LIMIT = 4;
    private const SUGGEST_LIMIT = 6;

    public function __construct(
        private readonly Product $products = new Product(),
        private readonly Category $categories = new Category(),
    ) {
    }

    /**
     * @param array<string,mixed> $query Ham GET verisi (beyaz listeyle normalize edilir)
     * @return array{products:array,filters:array,sort:string}
     */
    public function listProducts(array $query, ?int $categoryId = null, ?int $regionId = null): array
    {
        // İstemci girdisi güvenilmez: yalnızca bilinen anahtarlar ve değerler kabul edilir.
        $sortRaw = $query['sort'] ?? null;
        $sort = is_string($sortRaw) && in_array($sortRaw, self::SORTS, true) ? $sortRaw : 'newest';

        $pageRaw = $query['page'] ?? 1;
        $page = (is_string($pageRaw) || is_int($pageRaw)) && preg_match('/^\d{1,5}$/', (string) $pageRaw) === 1
            ? max(1, min(self::MAX_PAGE, (int) $pageRaw))
            : 1;

        $min = self::normalizePrice($query['min_price'] ?? null);
        $max = self::normalizePrice($query['max_price'] ?? null);
        if ($min !== null && $max !== null && Money::toCents($min) > Money::toCents($max)) {
            [$min, $max] = [$max, $min];
        }

        $q = self::normalizeQuery($query['q'] ?? '');
        $inStock = self::flag($query['in_stock'] ?? null);
        $onSale = self::flag($query['indirimli'] ?? ($query['on_sale'] ?? null));

        $modelFilters = [];
        if ($categoryId !== null && $categoryId > 0) {
            $modelFilters['category_id'] = $categoryId;
        }
        if ($regionId !== null && $regionId > 0) {
            $modelFilters['region_id'] = $regionId;
        }
        if ($min !== null) {
            $modelFilters['min_price'] = $min;
        }
        if ($max !== null) {
            $modelFilters['max_price'] = $max;
        }
        if ($inStock) {
            $modelFilters['in_stock'] = 1;
        }
        if ($onSale) {
            $modelFilters['on_sale'] = 1;
        }
        if ($q !== '') {
            $modelFilters['q'] = $q;
        }

        $products = $this->products->listActive($modelFilters, $sort, $page, self::PER_PAGE);

        return [
            'products' => $products,
            'filters' => [
                'q' => $q,
                'min_price' => $min,
                'max_price' => $max,
                'in_stock' => $inStock,
                'on_sale' => $onSale,
                'page' => $page,
                'category_id' => $categoryId,
                'region_id' => $regionId,
            ],
            'sort' => $sort,
        ];
    }

    /**
     * Normalize edilmiş filtrelerden URL sorgu parametreleri üretir (sayfa hariç).
     *
     * @param array<string,mixed> $filters
     * @return array<string,string>
     */
    public static function buildQuery(array $filters, string $sort): array
    {
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $params['q'] = (string) $filters['q'];
        }
        if (($filters['min_price'] ?? null) !== null) {
            $params['min_price'] = (string) $filters['min_price'];
        }
        if (($filters['max_price'] ?? null) !== null) {
            $params['max_price'] = (string) $filters['max_price'];
        }
        if (!empty($filters['in_stock'])) {
            $params['in_stock'] = '1';
        }
        if (!empty($filters['on_sale'])) {
            $params['indirimli'] = '1';
        }
        if ($sort !== 'newest') {
            $params['sort'] = $sort;
        }

        return $params;
    }

    /**
     * Arama metnini temizler; en az 2 karakter değilse boş döner.
     */
    public static function normalizeQuery(mixed $raw): string
    {
        if (!is_string($raw)) {
            return '';
        }
        $q = preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $raw);
        if (!is_string($q)) {
            return '';
        }
        $q = trim($q);
        if (mb_strlen($q, 'UTF-8') < self::MIN_QUERY) {
            return '';
        }

        return mb_substr($q, 0, self::MAX_QUERY, 'UTF-8');
    }

    /**
     * Ürün detayı + varyant seçici için JSON hazır dizi.
     * Müşteriye TAM stok sayısı verilmez; yalnızca in_stock ve low bayrakları.
     *
     * @return array{product:array,images:array,variants:array,default_variant_id:int,related:array,category_trail:array}|null
     */
    public function productDetail(string $slug): ?array
    {
        $product = $this->products->findActiveBySlug($slug);
        if ($product === null) {
            return null;
        }

        $variants = [];
        $defaultId = 0;
        $firstInStockId = 0;
        foreach ((array) ($product['variants'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $payload = $this->variantPayload($row);
            $variants[] = $payload;
            if ($defaultId === 0 && (int) ($row['is_default'] ?? 0) === 1 && $payload['in_stock']) {
                $defaultId = $payload['id'];
            }
            if ($firstInStockId === 0 && $payload['in_stock']) {
                $firstInStockId = $payload['id'];
            }
        }
        if ($variants === []) {
            // Satılabilir varyantı olmayan ürün vitrinde gösterilmez.
            return null;
        }
        if ($defaultId === 0) {
            $defaultId = $firstInStockId > 0 ? $firstInStockId : $variants[0]['id'];
        }

        $images = array_values((array) ($product['images'] ?? []));
        usort(
            $images,
            static fn (array $a, array $b): int => ((int) ($b['is_primary'] ?? 0)) <=> ((int) ($a['is_primary'] ?? 0))
        );

        return [
            'product' => $product,
            'images' => $images,
            'variants' => $variants,
            'default_variant_id' => $defaultId,
            'related' => $this->products->related((int) $product['id'], self::RELATED_LIMIT),
            'category_trail' => $this->categoryTrail((array) ($product['category'] ?? [])),
        ];
    }

    /**
     * Arama önerileri (en çok 6): ad, slug, görsel. Yalnızca aktif ürünler (model garantisi).
     *
     * @return list<array{name:string,slug:string,image:?string}>
     */
    public function suggest(string $q): array
    {
        $q = self::normalizeQuery($q);
        if ($q === '') {
            return [];
        }

        $out = [];
        foreach (array_slice($this->products->search($q, self::SUGGEST_LIMIT), 0, self::SUGGEST_LIMIT) as $row) {
            $image = $row['image_path'] ?? null;
            $out[] = [
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'image' => is_string($image) && $image !== '' ? $image : null,
            ];
        }

        return $out;
    }

    /**
     * Kategori yolu (kökten yaprağa): [['id','name','slug'], ...]. Geçerli kategori sonda garanti edilir.
     *
     * @param array<string,mixed> $category
     * @return list<array{id:int,name:string,slug:string}>
     */
    public function categoryTrail(array $category): array
    {
        $trail = [];
        $id = (int) ($category['id'] ?? 0);
        if ($id > 0) {
            foreach ($this->categories->breadcrumb($id) as $row) {
                if (is_array($row) && isset($row['name'], $row['slug'])) {
                    $trail[] = [
                        'id' => (int) ($row['id'] ?? 0),
                        'name' => (string) $row['name'],
                        'slug' => (string) $row['slug'],
                    ];
                }
            }
        }

        $last = $trail === [] ? null : $trail[array_key_last($trail)];
        if (($last === null || $last['slug'] !== (string) ($category['slug'] ?? '')) && isset($category['name'], $category['slug'])) {
            $trail[] = ['id' => $id, 'name' => (string) $category['name'], 'slug' => (string) $category['slug']];
        }

        return $trail;
    }

    /**
     * @param array<string,mixed> $v
     * @return array{id:int,name:string,sku:string,final_price:string,price:string,on_sale:bool,in_stock:bool,low:bool,weight_grams:int}
     */
    private function variantPayload(array $v): array
    {
        $track = (int) ($v['track_stock'] ?? 1) === 1;
        $stock = (int) ($v['stock_quantity'] ?? 0);
        $threshold = (int) ($v['low_stock_threshold'] ?? 0);

        return [
            'id' => (int) $v['id'],
            'name' => (string) $v['name'],
            'sku' => (string) $v['sku'],
            'final_price' => (string) $v['final_price'],
            'price' => (string) $v['price'],
            'on_sale' => (bool) ($v['on_sale'] ?? false),
            'in_stock' => !$track || $stock > 0,
            'low' => $track && $stock > 0 && $stock <= $threshold,
            'weight_grams' => (int) ($v['weight_grams'] ?? 0),
        ];
    }

    private static function normalizePrice(mixed $raw): ?string
    {
        if (!is_string($raw) && !is_int($raw)) {
            return null;
        }
        $v = str_replace(',', '.', trim((string) $raw));
        if ($v === '' || preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v) !== 1) {
            return null;
        }
        try {
            $cents = Money::toCents($v); // para hesabı tamsayı kuruş
        } catch (InvalidArgumentException) {
            return null;
        }
        if ($cents < 0 || $cents > self::MAX_PRICE_CENTS) {
            return null;
        }

        return Money::fromCents($cents);
    }

    private static function flag(mixed $raw): bool
    {
        return $raw === '1' || $raw === 1 || $raw === true || $raw === 'on';
    }
}