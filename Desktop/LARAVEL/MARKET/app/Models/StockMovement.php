<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'product_id',
        'user_id',
        'purchase_id',
        'sale_id',
        'movement_type',
        'quantity',
        'stock_before',
        'stock_after',
        'note',
    ];

    /**
     * Alan tipi dönüşümleri.
     */
    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after'  => 'decimal:3',
        ];
    }

    /* ---------------------------------------------------------------
     | İlişkiler
     |---------------------------------------------------------------*/

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Hareketi kaydeden kullanıcı.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /* ---------------------------------------------------------------
     | Scope'lar
     |---------------------------------------------------------------*/

    /**
     * Hareket türüne göre filtre
     * (purchase, sale, return, damage, correction, initial).
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('movement_type', $type);
    }
}