<?php
// app/Models/ProductVariant.php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use LogicException;

final class ProductVariant extends BaseModel
{
    protected string $table = 'product_variants';
    protected bool $softDeletes = true;

    protected array $fillable = [
        'product_id',
        'sku',
        'barcode',
        'name',
        'weight_grams',
        'price',
        'sale_price',
        'sale_starts_at',
        'sale_ends_at',
        'stock_quantity',
        'low_stock_threshold',
        'track_stock',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected array $extraColumns = ['created_at', 'updated_at'];

    /**
     * Geçerli (indirim penceresi içindeki) fiyatı hesaplayan SQL ifadesi.
     * Ürün liste sorguları da aynı ifadeyi kullanır: tek doğruluk kaynağı.
     */
    public static function finalPriceSql(string $alias = 'v'): string
    {
        $a = self::safeAlias($alias);

        return '(CASE WHEN ' . self::saleActiveSql($a) . ' THEN ' . $a . '.sale_price ELSE ' . $a . '.price END)';
    }

    /** İndirimin şu an geçerli olup olmadığını (1/0) veren SQL ifadesi. */
    public static function onSaleSql(string $alias = 'v'): string
    {
        return '(' . self::saleActiveSql(self::safeAlias($alias)) . ')';
    }

    /** @return list<array> final_price ve on_sale dahil. */
    public function forProduct(int $productId, bool $onlyActive = true): array
    {
        $rows = $this->db->fetchAll(
            'SELECT v.*, ' . self::finalPriceSql('v') . ' AS final_price, ' . self::onSaleSql('v') . ' AS on_sale '
            . 'FROM `product_variants` v WHERE v.`product_id` = :product_id AND v.`deleted_at` IS NULL'
            . ($onlyActive ? ' AND v.`is_active` = 1' : '')
            . ' ORDER BY v.`is_default` DESC, v.`sort_order` ASC, v.`id` ASC',
            [':product_id' => $productId]
        );

        return array_map([$this, 'mapRow'], $rows);
    }

    /**
     * Sepet/ödeme için: yalnızca aktif varyant + aktif ürün. Anahtar varyant id'si.
     * Ek anahtarlar: final_price, on_sale, product_name, tax_rate.
     *
     * @return array<int, array>
     */
    public function findActiveMany(array $ids): array
    {
        $ids = $this->positiveInts($ids);
        if ($ids === []) {
            return [];
        }

        [$in, $params] = $this->inClause('v.`id`', $ids, 'vid');
        $rows = $this->db->fetchAll(
            'SELECT v.*, ' . self::finalPriceSql('v') . ' AS final_price, ' . self::onSaleSql('v') . ' AS on_sale, '
            . 'p.`name` AS product_name, p.`tax_rate` AS tax_rate '
            . 'FROM `product_variants` v '
            . 'INNER JOIN `products` p ON p.`id` = v.`product_id` AND p.`is_active` = 1 AND p.`deleted_at` IS NULL '
            . 'WHERE ' . $in . ' AND v.`is_active` = 1 AND v.`deleted_at` IS NULL',
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $row = $this->mapRow($row);
            $map[$row['id']] = $row;
        }

        return $map;
    }

    /**
     * Sipariş akışında satırları kilitler (SELECT ... FOR UPDATE). Yalnızca açık
     * transaction içinde çağrılabilir; kimlik sırasıyla kilitlenir (deadlock azaltma).
     *
     * @return array<int, array>
     */
    public function lockManyForUpdate(array $ids): array
    {
        if (!$this->db->pdo()->inTransaction()) {
            throw new LogicException('lockManyForUpdate() yalnızca açık bir transaction içinde çağrılabilir.');
        }

        $ids = $this->positiveInts($ids);
        if ($ids === []) {
            return [];
        }
        sort($ids);

        [$in, $params] = $this->inClause('v.`id`', $ids, 'vid');
        $rows = $this->db->fetchAll(
            'SELECT v.*, ' . self::finalPriceSql('v') . ' AS final_price, ' . self::onSaleSql('v') . ' AS on_sale '
            . 'FROM `product_variants` v WHERE ' . $in . ' AND v.`deleted_at` IS NULL ORDER BY v.`id` ASC FOR UPDATE',
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $row = $this->mapRow($row);
            $map[$row['id']] = $row;
        }

        return $map;
    }

    /** Tek atomik UPDATE: stok yetersizse hiçbir satır etkilenmez ve false döner. */
    public function decrementStock(int $id, int $qty): bool
    {
        if ($qty < 1) {
            throw new InvalidArgumentException('Miktar en az 1 olmalıdır.');
        }

        return $this->db->execute(
            'UPDATE `product_variants` SET `stock_quantity` = `stock_quantity` - :qty '
            . 'WHERE `id` = :id AND `deleted_at` IS NULL AND `stock_quantity` >= :min_qty',
            [':qty' => $qty, ':id' => $id, ':min_qty' => $qty]
        ) === 1;
    }

    /** İptal/iade dönüşü için; silinmiş varyantlara da iade yapılabilir. */
    public function incrementStock(int $id, int $qty): void
    {
        if ($qty < 1) {
            throw new InvalidArgumentException('Miktar en az 1 olmalıdır.');
        }

        $this->db->execute(
            'UPDATE `product_variants` SET `stock_quantity` = `stock_quantity` + :qty WHERE `id` = :id',
            [':qty' => $qty, ':id' => $id]
        );
    }

    /** @return list<array> v_low_stock görünümü (stok artan). */
    public function lowStock(): array
    {
        return $this->db->fetchAll('SELECT * FROM `v_low_stock`');
    }

    /** Silinmiş kayıtlar da sayılır: sku UNIQUE indeksi onları da kapsar. */
    public function skuExists(string $sku, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `product_variants` WHERE `sku` = :sku';
        $params = [':sku' => $sku];
        if ($exceptId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $params[':except_id'] = $exceptId;
        }

        return (int) $this->db->fetchValue($sql, $params) > 0;
    }

    private static function saleActiveSql(string $a): string
    {
        return $a . '.sale_price IS NOT NULL'
            . ' AND (' . $a . '.sale_starts_at IS NULL OR ' . $a . '.sale_starts_at <= NOW())'
            . ' AND (' . $a . '.sale_ends_at IS NULL OR ' . $a . '.sale_ends_at >= NOW())';
    }

    private static function safeAlias(string $alias): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $alias) !== 1) {
            throw new InvalidArgumentException('Geçersiz tablo takma adı.');
        }

        return $alias;
    }

    private function mapRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['on_sale'] = (bool) $row['on_sale'];

        return $row;
    }
}