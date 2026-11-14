<?php
// app/Models/CartItem.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Sepet kalemleri (cart_items). Fiyat saklanmaz; canlı fiyat varyanttan hesaplanır. */
class CartItem extends BaseModel
{
    protected string $table = 'cart_items';
    protected string $primaryKey = 'id';
    protected array $fillable = ['cart_id', 'variant_id', 'quantity'];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'created_at'];

    public function forCart(int $cartId): array
    {
        $sql = "SELECT ci.variant_id, v.product_id, p.name AS product_name, p.slug,
                       v.name AS variant_name, v.sku,
                       (SELECT pi.path FROM product_images pi
                         WHERE pi.product_id = p.id
                         ORDER BY (pi.variant_id = v.id) DESC, pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                         LIMIT 1) AS image_path,
                       ci.quantity, v.price, v.sale_price, v.sale_starts_at, v.sale_ends_at,
                       CASE WHEN v.sale_price IS NOT NULL
                                 AND (v.sale_starts_at IS NULL OR v.sale_starts_at <= NOW())
                                 AND (v.sale_ends_at IS NULL OR v.sale_ends_at >= NOW())
                            THEN v.sale_price ELSE v.price END AS final_price,
                       v.stock_quantity, v.track_stock, v.weight_grams, p.tax_rate,
                       (v.is_active = 1 AND p.is_active = 1) AS is_active
                  FROM cart_items ci
                  INNER JOIN product_variants v ON v.id = ci.variant_id
                  INNER JOIN products p ON p.id = v.product_id
                 WHERE ci.cart_id = :c AND v.deleted_at IS NULL AND p.deleted_at IS NULL
                 ORDER BY ci.id ASC";
        $rows = Database::instance()->fetchAll($sql, [':c' => $cartId]);
        foreach ($rows as &$r) {
            foreach (['variant_id', 'product_id', 'quantity', 'stock_quantity', 'track_stock', 'weight_grams', 'is_active'] as $k) {
                $r[$k] = (int) $r[$k];
            }
        }
        unset($r);
        return $rows;
    }

    /** Ekler; kalem varsa miktarı toplar, toplam 99'u aşamaz (atomik). */
    public function upsertAdd(int $cartId, int $variantId, int $qty): void
    {
        if ($qty < 1) {
            throw new \InvalidArgumentException('Miktar en az 1 olmalı.');
        }
        $qty = min(99, $qty);
        Database::instance()->execute(
            'INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (:c, :v, :q)
             ON DUPLICATE KEY UPDATE quantity = LEAST(99, quantity + :add)',
            [':c' => $cartId, ':v' => $variantId, ':q' => $qty, ':add' => $qty]
        );
    }

    public function setQuantity(int $cartId, int $variantId, int $qty): void
    {
        if ($qty < 1 || $qty > 99) {
            throw new \InvalidArgumentException('Miktar 1 ile 99 arasında olmalı.');
        }
        Database::instance()->execute(
            'UPDATE cart_items SET quantity = :q WHERE cart_id = :c AND variant_id = :v',
            [':q' => $qty, ':c' => $cartId, ':v' => $variantId]
        );
    }

    public function remove(int $cartId, int $variantId): void
    {
        Database::instance()->execute(
            'DELETE FROM cart_items WHERE cart_id = :c AND variant_id = :v',
            [':c' => $cartId, ':v' => $variantId]
        );
    }

    public function clear(int $cartId): void
    {
        Database::instance()->execute('DELETE FROM cart_items WHERE cart_id = :c', [':c' => $cartId]);
    }

    /** Rozet için toplam adet (miktarların toplamı). */
    public function countItems(int $cartId): int
    {
        return (int) Database::instance()->fetchValue(
            'SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = :c',
            [':c' => $cartId]
        );
    }
}