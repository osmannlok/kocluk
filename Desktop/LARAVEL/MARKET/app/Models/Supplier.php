<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'tax_number',
        'address',
        'balance',
        'is_active',
    ];

    /**
     * Alan tipi dönüşümleri.
     */
    protected function casts(): array
    {
        return [
            'balance'   => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /* ---------------------------------------------------------------
     | İlişkiler
     |---------------------------------------------------------------*/

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /* ---------------------------------------------------------------
     | Scope'lar
     |---------------------------------------------------------------*/

    /**
     * Aktif tedarikçiler.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Borcu olan tedarikçiler (bakiye > 0).
     */
    public function scopeWithDebt(Builder $query): Builder
    {
        return $query->where('balance', '>', 0);
    }
}