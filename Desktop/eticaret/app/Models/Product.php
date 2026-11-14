<?php
// app/Models/Product.php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Money;
use InvalidArgumentException;

final class Product extends BaseModel
{
    /** Sıralama anahtarı => ORDER BY ifadesi (beyaz liste; kullanıcı girdisi SQL'e girmez). */
    private const SORTS = [
        'newest' => 'p.created_at DESC, p.id DESC',
        'price_asc' => 'final_price ASC, p.id ASC',
        'price_desc' => 'final_price DESC, p.id DESC',
        'name' => 'p.name ASC, p.id ASC',
    ];

    private const ACTIVE_WHERE = 'p.is_active = 1 AND p.deleted_at IS NULL';

    protected string $table = 'products';
    protected bool $softDeletes = true;

    protected array $fillable = [
        'category_id',
        'region_id',
        'name',
        'slug',
        'short_description',
        'description',
        'origin_text',
        'producer_name',
        'ingredients',
        'allergen_info',
        'storage_conditions',
        'shelf_life_info',
        'tax_rate',
        'is_active',
        'is_featured',
        'meta_title',
        'meta_description',
        'created_by',
        'updated_by',
    ];

    protected array $extraColumns = ['created_at', 'updated_at'];

    /**
     * Ürün sütunları + region_name + category{id,name,slug} + variants[] + images[].
     * Yalnızca aktif, silinmemiş ve aktif kategorideki ürün döner.
     */
    public function findActiveBySlug(string $slug): ?array
    {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 220) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT p.*, r.`name` AS region_name, c.`id` AS cat_id, c.`name` AS cat_name, c.`slug` AS cat_slug '
            . 'FROM `products` p '
            . 'INNER JOIN `categories` c ON c.`id` = p.`category_id` AND c.`is_active` = 1 AND c.`deleted_at` IS NULL '
            . 'LEFT JOIN `regions` r ON r.`id` = p.`region_id` AND r.`is_active` = 1 '
            . 'WHERE p.`slug` = :slug AND ' . self::ACTIVE_WHERE . ' LIMIT 1',
            [':slug' => $slug]
        );
        if ($row === null) {
            return null;
        }

        $row['category'] = [
            'id' => (int) $row['cat_id'],
            'name' => (string) $row['cat_name'],
            'slug' => (string) $row['cat_slug'],
        ];
        // Personel kimlikleri vitrine sızmasın; yardımcı takma adlar temizlenir.
        unset($row['cat_id'], $row['cat_name'], $row['cat_slug'], $row['created_by'], $row['updated_by']);

        $productId = (int) $row['id'];
        $row['id'] = $productId;
        $row['variants'] = (new ProductVariant())->forProduct($productId, true);
        $row['images'] = (new ProductImage())->forProduct($productId);

        return $row;
    }

    /**
     * Filtreler: category_id (alt kategoriler dahil), region_id, min_price, max_price,
     * in_stock, q; ek: on_sale. Sıralama: newest|price_asc|price_desc|name.
     *
     * @return array{items: list<array>, total: int, page: int, per_page: int, pages: int}
     */
    public function listActive(array $filters, string $sort, int $page, int $perPage): array
    {
        [$clauses, $params] = $this->activeFilters($filters);
        $orderBy = self::SORTS[$sort] ?? self::SORTS['newest'];

        $result = $this->paginateRaw(
            $this->listColumns(),
            $this->activeFrom(),
            implode(' AND ', $clauses),
            $params,
            $orderBy,
            $page,
            $perPage
        );
        $result['items'] = array_map([$this, 'mapListRow'], $result['items']);

        return $result;
    }

    /** @return list<array> Ad öneki eşleşenler öne alınır. */
    public function search(string $q, int $limit): array
    {
        $q = trim(mb_substr($q, 0, 100));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $limit = $this->clamp($limit, 1, 50);

        $escaped = self::escapeLike($q);
        $params = [
            ':q_name' => '%' . $escaped . '%',
            ':q_desc' => '%' . $escaped . '%',
            ':q_prefix' => $escaped . '%',
        ];

        $sql = 'SELECT ' . $this->listColumns() . ' FROM ' . $this->activeFrom()
            . ' WHERE ' . self::ACTIVE_WHERE
            . ' AND (' . $this->likeSql('p.name', ':q_name') . ' OR ' . $this->likeSql('p.short_description', ':q_desc') . ')'
            . ' ORDER BY CASE WHEN ' . $this->likeSql('p.name', ':q_prefix') . ' THEN 0 ELSE 1 END ASC, p.name ASC, p.id ASC'
            . ' LIMIT ' . $limit;

        return array_map([$this, 'mapListRow'], $this->db->fetchAll($sql, $params));
    }

    /** @return list<array> */
    public function featured(int $limit): array
    {
        return $this->fetchActiveRows(
            ' AND p.is_featured = 1',
            [],
            'p.created_at DESC, p.id DESC',
            $this->clamp($limit, 1, 24)
        );
    }

    /** @return list<array> Aynı kategorideki diğer ürünler (öne çıkanlar önce). */
    public function related(int $productId, int $limit): array
    {
        return $this->fetchActiveRows(
            ' AND p.id <> :product_id AND p.category_id = (SELECT rp.category_id FROM products rp WHERE rp.id = :product_ref LIMIT 1)',
            [':product_id' => $productId, ':product_ref' => $productId],
            'p.is_featured DESC, p.created_at DESC, p.id DESC',
            $this->clamp($limit, 1, 24)
        );
    }

    /** Silinmiş kayıtlar da sayılır: slug UNIQUE indeksi onları da kapsar. */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `products` WHERE `slug` = :slug';
        $params = [':slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $params[':except_id'] = $exceptId;
        }

        return (int) $this->db->fetchValue($sql, $params) > 0;
    }

// app/Models/Product.php: adminList() metodu (docblock dahil, 26-94. satırların yerine geçer)

    /**
     * Yönetim listesi. Filtreler: q (ad/slug/sku), category_id (alt kategoriler dahil),
     * region_id, is_active, is_featured, trashed. Liste satırı anahtarlarına ek olarak:
     * is_active (bool), is_featured (bool), category_id (int), category_name (string),
     * created_at, updated_at, deleted_at,
     * variant_count (int): aktif ve silinmemiş varyant sayısı, yoksa 0,
     * min_price / max_price (?string "1234.50"): aktif ve silinmemiş varyantların final_price en düşüğü / en yükseği, varyant yoksa NULL,
     * stock_total (?int): aktif, silinmemiş ve track_stock=1 varyantların stok toplamı; takip edilen varyant yoksa NULL (0 DEĞİL),
     * low_stock (bool): takip edilen en az bir varyantta stock_quantity <= low_stock_threshold.
     *
     * @return array{items: list<array>, total: int, page: int, per_page: int, pages: int}
     */
    public function adminList(array $filters, int $page, int $perPage): array
    {
        $params = [];
        $clauses = [$this->truthy($filters['trashed'] ?? null) ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL'];

        $q = isset($filters['q']) && is_string($filters['q']) ? trim(mb_substr($filters['q'], 0, 100)) : '';
        if ($q !== '') {
            $like = '%' . self::escapeLike($q) . '%';
            $clauses[] = '(' . implode(' OR ', [
                $this->likeSql('p.name', ':q_name'),
                $this->likeSql('p.slug', ':q_slug'),
                'EXISTS (SELECT 1 FROM product_variants qv WHERE qv.product_id = p.id AND '
                    . $this->likeSql('qv.sku', ':q_sku') . ')',
            ]) . ')';
            $params[':q_name'] = $like;
            $params[':q_slug'] = $like;
            $params[':q_sku'] = $like;
        }

        $categoryId = (int) ($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $ids = array_merge([$categoryId], (new Category())->descendantIds($categoryId));
            [$in, $inParams] = $this->inClause('p.category_id', $ids, 'cat');
            $clauses[] = $in;
            $params += $inParams;
        }

        $regionId = (int) ($filters['region_id'] ?? 0);
        if ($regionId > 0) {
            $clauses[] = 'p.region_id = :region_id';
            $params[':region_id'] = $regionId;
        }

        foreach (['is_active', 'is_featured'] as $flag) {
            if (array_key_exists($flag, $filters) && $filters[$flag] !== '' && $filters[$flag] !== null) {
                $clauses[] = 'p.' . $flag . ' = :' . $flag;
                $params[':' . $flag] = $this->truthy($filters[$flag]) ? 1 : 0;
            }
        }

        // Varyant toplamları tek türetilmiş tabloda (GROUP BY product_id); satır başına alt sorgu yok.
        // final_price ifadesi listActive ile AYNI parçadır: ProductVariant::finalPriceSql (kopya yok).
        // SUM(CASE ...) takip edilen varyant yoksa NULL döner; stok toplamında 0 ile "takipsiz" ayrımı korunur.
        $finalPrice = ProductVariant::finalPriceSql('sv');
        $stats = 'SELECT sv.product_id, COUNT(*) AS variant_count, '
            . 'MIN(CAST(' . $finalPrice . ' AS DECIMAL(12,2))) AS min_price, '
            . 'MAX(CAST(' . $finalPrice . ' AS DECIMAL(12,2))) AS max_price, '
            . 'SUM(CASE WHEN sv.track_stock = 1 THEN sv.stock_quantity END) AS stock_total, '
            . 'MAX(CASE WHEN sv.track_stock = 1 AND sv.stock_quantity <= sv.low_stock_threshold THEN 1 ELSE 0 END) AS low_stock '
            . 'FROM product_variants sv WHERE sv.is_active = 1 AND sv.deleted_at IS NULL GROUP BY sv.product_id';

        $columns = $this->listColumns() . ', p.is_active, p.is_featured, p.category_id, c.name AS category_name, '
            . 'p.created_at, p.updated_at, p.deleted_at, '
            . 'COALESCE(vt.variant_count, 0) AS variant_count, vt.min_price, vt.max_price, '
            . 'vt.stock_total, COALESCE(vt.low_stock, 0) AS low_stock';

        // vt ürün başına en çok 1 satır üretir: JOIN satır sayısını (COUNT/total) değiştirmez.
        $from = 'products p '
            . 'INNER JOIN categories c ON c.id = p.category_id '
            . 'LEFT JOIN regions r ON r.id = p.region_id '
            . 'LEFT JOIN product_variants dv ON dv.id = (' . $this->defaultVariantSql(false) . ') '
            . 'LEFT JOIN (' . $stats . ') vt ON vt.product_id = p.id';

        $result = $this->paginateRaw($columns, $from, implode(' AND ', $clauses), $params, 'p.id DESC', $page, $perPage);
        $result['items'] = array_map(function (array $row): array {
            $row = $this->mapListRow($row);
            $row['is_active'] = (bool) $row['is_active'];
            $row['is_featured'] = (bool) $row['is_featured'];
            $row['category_id'] = (int) $row['category_id'];
            $row['variant_count'] = (int) $row['variant_count'];
            // NULL korunur (takipsiz/varyantsız); float kullanılmaz, kuruş üzerinden "1234.50" string.
            $row['stock_total'] = $row['stock_total'] === null ? null : (int) $row['stock_total'];
            $row['min_price'] = $row['min_price'] === null
                ? null
                : \App\Helpers\Money::fromCents(\App\Helpers\Money::toCents((string) $row['min_price']));
            $row['max_price'] = $row['max_price'] === null
                ? null
                : \App\Helpers\Money::fromCents(\App\Helpers\Money::toCents((string) $row['max_price']));
            $row['low_stock'] = (int) $row['low_stock'] === 1;

            return $row;
        }, $result['items']);

        return $result;
    }

    // ------------------------------------------------------------------
    // Özel yardımcılar
    // ------------------------------------------------------------------

    /**
     * Vitrin filtrelerini WHERE parçalarına çevirir. Her kullanıcı değeri parametredir.
     *
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private function activeFilters(array $filters): array
    {
        $clauses = [self::ACTIVE_WHERE];
        $params = [];

        $categoryId = (int) ($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $ids = array_merge([$categoryId], (new Category())->descendantIds($categoryId));
            [$in, $inParams] = $this->inClause('p.category_id', $ids, 'cat');
            $clauses[] = $in;
            $params += $inParams;
        }

        $regionId = (int) ($filters['region_id'] ?? 0);
        if ($regionId > 0) {
            $clauses[] = 'p.region_id = :region_id';
            $params[':region_id'] = $regionId;
        }

        $finalPrice = ProductVariant::finalPriceSql('dv');

        $min = $this->priceParam($filters['min_price'] ?? null);
        if ($min !== null) {
            $clauses[] = $finalPrice . ' >= CAST(:min_price AS DECIMAL(12,2))';
            $params[':min_price'] = $min;
        }

        $max = $this->priceParam($filters['max_price'] ?? null);
        if ($max !== null) {
            $clauses[] = $finalPrice . ' <= CAST(:max_price AS DECIMAL(12,2))';
            $params[':max_price'] = $max;
        }

        if ($this->truthy($filters['in_stock'] ?? null)) {
            $clauses[] = $this->inStockSql();
        }

        if ($this->truthy($filters['on_sale'] ?? null)) {
            $clauses[] = ProductVariant::onSaleSql('dv');
        }

        $q = isset($filters['q']) && is_string($filters['q']) ? trim(mb_substr($filters['q'], 0, 100)) : '';
        if (mb_strlen($q) >= 2) {
            $like = '%' . self::escapeLike($q) . '%';
            $clauses[] = '(' . $this->likeSql('p.name', ':q_name') . ' OR ' . $this->likeSql('p.short_description', ':q_desc') . ')';
            $params[':q_name'] = $like;
            $params[':q_desc'] = $like;
        }

        return [$clauses, $params];
    }

    /**
     * @param string $extraWhere ' AND ...' ile başlayan sabit SQL parçası (kullanıcı girdisi içermez)
     * @return list<array>
     */
    private function fetchActiveRows(string $extraWhere, array $params, string $orderBy, int $limit): array
    {
        $sql = 'SELECT ' . $this->listColumns() . ' FROM ' . $this->activeFrom()
            . ' WHERE ' . self::ACTIVE_WHERE . $extraWhere
            . ' ORDER BY ' . $orderBy . ' LIMIT ' . $limit;

        return array_map([$this, 'mapListRow'], $this->db->fetchAll($sql, $params));
    }

    /** Liste satırı kolonları: dv = varsayılan varyant, r = yöre. */
    private function listColumns(): string
    {
        return 'p.id, p.name, p.slug, p.short_description, r.name AS region_name, '
            . '(SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id '
            . 'ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image_path, '
            . 'CAST(COALESCE(dv.price, 0) AS DECIMAL(12,2)) AS price, '
            . 'CAST(COALESCE(' . ProductVariant::finalPriceSql('dv') . ', 0) AS DECIMAL(12,2)) AS final_price, '
            . 'COALESCE(' . ProductVariant::onSaleSql('dv') . ', 0) AS on_sale, '
            . $this->inStockSql() . ' AS in_stock, '
            . 'dv.id AS default_variant_id';
    }

    /** Vitrin FROM: aktif kategori + varsayılan (aktif) varyantı olan ürünler. */
    private function activeFrom(): string
    {
        return 'products p '
            . 'INNER JOIN categories c ON c.id = p.category_id AND c.is_active = 1 AND c.deleted_at IS NULL '
            . 'LEFT JOIN regions r ON r.id = p.region_id AND r.is_active = 1 '
            . 'INNER JOIN product_variants dv ON dv.id = (' . $this->defaultVariantSql(true) . ')';
    }

    private function defaultVariantSql(bool $onlyActive): string
    {
        return 'SELECT v.id FROM product_variants v WHERE v.product_id = p.id AND v.deleted_at IS NULL'
            . ($onlyActive ? ' AND v.is_active = 1' : '')
            . ' ORDER BY v.is_default DESC, v.sort_order ASC, v.id ASC LIMIT 1';
    }

    /** Aktif varyantlardan en az birinde stok > 0 veya stok takibi kapalı. */
    private function inStockSql(): string
    {
        return 'EXISTS (SELECT 1 FROM product_variants sv WHERE sv.product_id = p.id AND sv.is_active = 1 '
            . 'AND sv.deleted_at IS NULL AND (sv.track_stock = 0 OR sv.stock_quantity > 0))';
    }

    private function mapListRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['price'] = (string) $row['price'];
        $row['final_price'] = (string) $row['final_price'];
        $row['on_sale'] = (bool) $row['on_sale'];
        $row['in_stock'] = (bool) $row['in_stock'];
        $row['default_variant_id'] = $row['default_variant_id'] !== null ? (int) $row['default_variant_id'] : null;

        return $row;
    }

    private function truthy(mixed $value): bool
    {
        return $value !== null && filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** Geçerli, negatif olmayan tutarı "1234.50" biçimine getirir; aksi halde null. */
    private function priceParam(mixed $value): ?string
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || $value === '') {
            return null;
        }

        try {
            $cents = Money::toCents($value);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $cents < 0 ? null : Money::fromCents($cents);
    }
}