<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stok hareketleri tablosu.
     * movement_type değerleri: purchase, sale, return, damage, correction, initial
     * Her stok değişimi bu tabloya yazılır; kayıtlar silinmez, yalnızca düzeltme kaydı eklenir.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type', 30);
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('stock_before', 12, 3)->default(0);
            $table->decimal('stock_after', 12, 3)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            // Ürün bazlı stok geçmişi için index
            $table->index(['product_id', 'created_at']);
            // Fire, iade ve sayım raporları için index
            $table->index(['movement_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};