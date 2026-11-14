<?php
// app/Models/OrderItem.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Sipariş kalemleri (order_items): fiyat/ürün adı anlık görüntüsü. */
class OrderItem extends BaseModel
{
    protected string $table = 'order_items';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'order_id', 'variant_id', 'product_name', 'variant_name', 'sku', 'list_price',
        'unit_price', 'quantity', 'tax_rate', 'line_total', 'weight_grams',
    ];
    protected bool $softDeletes = false;
    protected array $sortable = ['id'];

    /** Tüm kalemler tek transaction'da yazılır (dışarıda transaction varsa ona katılır). */
    public function insertMany(int $orderId, array $items): void
    {
        if ($items === []) {
            throw new \InvalidArgumentException('Sipariş kalemi boş olamaz.');
        }
        Database::instance()->transaction(function () use ($orderId, $items): void {
            foreach ($items as $it) {
                foreach (['variant_id', 'product_name', 'variant_name', 'sku', 'list_price', 'unit_price',
                          'quantity', 'tax_rate', 'line_total', 'weight_grams'] as $k) {
                    if (!array_key_exists($k, $it) || $it[$k] === null) {
                        throw new \InvalidArgumentException('Eksik kalem alanı: ' . $k);
                    }
                }
                $qty = (int) $it['quantity'];
                if ($qty < 1 || $qty > 99) {
                    throw new \InvalidArgumentException('Geçersiz kalem miktarı.');
                }
                foreach (['list_price', 'unit_price', 'line_total'] as $k) {
                    if (is_float($it[$k]) || preg_match('/^\d{1,10}(\.\d{1,2})?$/', (string) $it[$k]) !== 1) {
                        throw new \InvalidArgumentException('Geçersiz tutar: ' . $k);
                    }
                }
                if (preg_match('/^\d{1,3}(\.\d{1,2})?$/', (string) $it['tax_rate']) !== 1) {
                    throw new \InvalidArgumentException('Geçersiz KDV oranı.');
                }
                Database::instance()->insert(
                    'INSERT INTO order_items (order_id, variant_id, product_name, variant_name, sku, list_price,
                        unit_price, quantity, tax_rate, line_total, weight_grams)
                     VALUES (:o, :v, :pn, :vn, :sku, :lp, :up, :q, :tr, :lt, :w)',
                    [
                        ':o' => $orderId,
                        ':v' => (int) $it['variant_id'],
                        ':pn' => mb_substr((string) $it['product_name'], 0, 190),
                        ':vn' => mb_substr((string) $it['variant_name'], 0, 100),
                        ':sku' => mb_substr((string) $it['sku'], 0, 64),
                        ':lp' => (string) $it['list_price'],
                        ':up' => (string) $it['unit_price'],
                        ':q' => $qty,
                        ':tr' => (string) $it['tax_rate'],
                        ':lt' => (string) $it['line_total'],
                        ':w' => max(0, (int) $it['weight_grams']),
                    ]
                );
            }
        });
    }

    public function forOrder(int $orderId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM order_items WHERE order_id = :o ORDER BY id ASC',
            [':o' => $orderId]
        );
    }

    /** Satırlar: variant_id, sku, product_name, variant_name, total_quantity, total_revenue. */
    public function topProducts(int $days, int $limit): array
    {
        $days = max(1, min(366, $days));
        $limit = max(1, min(100, $limit));
        return Database::instance()->fetchAll(
            "SELECT oi.variant_id, oi.sku, oi.product_name, oi.variant_name,
                    SUM(oi.quantity) AS total_quantity, SUM(oi.line_total) AS total_revenue
               FROM order_items oi
               INNER JOIN orders o ON o.id = oi.order_id
              WHERE o.status IN ('paid', 'preparing', 'shipped', 'delivered')
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL " . $days . " DAY)
              GROUP BY oi.variant_id, oi.sku, oi.product_name, oi.variant_name
              ORDER BY total_quantity DESC, total_revenue DESC
              LIMIT " . $limit
        );
    }
}