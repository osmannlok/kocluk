<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'category_id',
        'supplier_id',
        'name',
        'slug',
        'sku',
        'unit',
        'purchase_price',
        'sale_price',
        'vat_rate',
        'stock',
        'critical_stock',
        'is_weighed',
        'is_active',
        'image',
        'description',
    ];

    /**
     * Alan tipi dönüşümleri.
     * Para: 2 hane, miktar: 3 hane, KDV: 2 hane.
     */
    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price'     => 'decimal:2',
            'vat_rate'       => 'decimal:2',
            'stock'          => 'decimal:3',
            'critical_stock' => 'decimal:3',
            'is_weighed'     => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    /* ---------------------------------------------------------------
     | İlişkiler
     |---------------------------------------------------------------*/

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function primaryBarcode(): HasOne
    {
        return $this->hasOne(ProductBarcode::class)->where('is_primary', true);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /* ---------------------------------------------------------------
     | Scope'lar
     |---------------------------------------------------------------*/

    /**
     * Stoğu kritik stok seviyesinin altına düşen ürünler.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<', 'critical_stock');
    }

    /**
     * Satışta seçilebilir (aktif) ürünler.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Ürün adı, SKU veya barkod ile arama.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhereHas('barcodes', function (Builder $b) use ($term) {
                    $b->where('barcode', 'like', "%{$term}%");
                });
        });
    }

    /* ---------------------------------------------------------------
     | Yardımcılar
     |---------------------------------------------------------------*/

    /**
     * Ürün kritik stok seviyesinin altında mı?
     */
    public function isLowStock(): bool
    {
        return bccomp((string) $this->stock, (string) $this->critical_stock, 3) < 0;
    }
}