<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ürünler tablosu.
     * unit değerleri: adet, kg, gram, litre, paket
     * Barkodlar ayrı tabloda (product_barcodes) tutulur.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->string('sku', 50)->nullable()->unique();
            $table->string('unit', 20)->default('adet');
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('critical_stock', 12, 3)->default(0);
            $table->boolean('is_weighed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Ürün adı ile arama için index
            $table->index('name');
            // Kategori filtresi ve aktif ürün listeleri için index
            $table->index(['category_id', 'is_active']);
            // Kritik stok sorguları için index
            $table->index(['stock', 'critical_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
