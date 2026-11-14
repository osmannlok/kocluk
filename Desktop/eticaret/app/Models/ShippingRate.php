<?php
// app/Models/ShippingRate.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Ağırlık bazlı kargo tarifesi (shipping_rates). */
class ShippingRate extends BaseModel
{
    protected string $table = 'shipping_rates';
    protected string $primaryKey = 'id';
    protected array $fillable = ['min_weight_grams', 'max_weight_grams', 'price', 'is_active'];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'min_weight_grams', 'max_weight_grams', 'price'];

    /** Gramaja uyan aktif tarife; örtüşmede en dar (en yüksek min) aralık kazanır. Yoksa null. */
    public function priceForWeight(int $grams): ?string
    {
        $v = Database::instance()->fetchValue(
            'SELECT price FROM shipping_rates
              WHERE is_active = 1 AND :g1 >= min_weight_grams AND :g2 <= max_weight_grams
              ORDER BY min_weight_grams DESC, id ASC LIMIT 1',
            [':g1' => $grams, ':g2' => $grams]
        );
        return ($v === false || $v === null) ? null : (string) $v;
    }

    public function activeAll(): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM shipping_rates WHERE is_active = 1 ORDER BY min_weight_grams ASC, id ASC'
        );
    }
}