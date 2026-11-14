<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    /**
     * Alan tipi dönüşümleri.
     */
    protected function casts(): array
    {
        return [
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

    /* ---------------------------------------------------------------
     | Scope'lar
     |---------------------------------------------------------------*/

    /**
     * Aktif kategoriler.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}