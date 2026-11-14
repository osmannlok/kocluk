<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'sale_no',
        'user_id',
        'customer_id',
        'sale_date',
        'subtotal',
        'discount',
        'vat_total',
        'total',
        'paid_total',
        'change_amount',
        'status',
        'note',
    ];

    /**
     * Alan tipi dönüşümleri.
     */
    protected function casts(): array
    {
        return [
            'sale_date'     => 'datetime',
            'subtotal'      => 'decimal:2',
            'discount'      => 'decimal:2',
            'vat_total'     => 'decimal:2',
            'total'         => 'decimal:2',
            'paid_total'    => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    /* ---------------------------------------------------------------
     | İlişkiler
     |---------------------------------------------------------------*/

    /**
     * Satışı yapan kasiyer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /* ---------------------------------------------------------------
     | Scope'lar
     |---------------------------------------------------------------*/

    /**
     * Belirtilen tarih aralığındaki satışlar (başlangıç ve bitiş dahil).
     */
    public function scopeBetweenDates(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('sale_date', [
            \Illuminate\Support\Carbon::parse($from)->startOfDay(),
            \Illuminate\Support\Carbon::parse($to)->endOfDay(),
        ]);
    }

    /**
     * Tamamlanmış satışlar.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
}