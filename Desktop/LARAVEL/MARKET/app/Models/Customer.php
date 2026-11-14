<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
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

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /* ---------------------------------------------------------------
     | Scope'lar
     |---------------------------------------------------------------*/

    /**
     * Aktif müşteriler.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Veresiye borcu olan müşteriler (bakiye > 0).
     */
    public function scopeWithDebt(Builder $query): Builder
    {
        return $query->where('balance', '>', 0);
    }
}