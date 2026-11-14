<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'purchase_price',
        'discount',
        'vat_rate',
        'total_price',
        'profit',
    ];

    /**
     * Alan tipi dönüşümleri.
     */
    protected function casts(): array
    {
        return [
            'quantity'       => 'decimal:3',
            'unit_price'     => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'discount'       => 'decimal:2',
            'vat_rate'       => 'decimal:2',
            'total_price'    => 'decimal:2',
            'profit'         => 'decimal:2',
        ];
    }

    /* ---------------------------------------------------------------
     | İlişkiler
     |---------------------------------------------------------------*/

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}