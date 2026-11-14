<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tedarikçi alımları (ürün girişi fişleri) tablosu.
     * status değerleri: completed, pending, cancelled
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_no', 30)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('purchase_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('completed');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Tarih bazlı raporlama için index
            $table->index('purchase_date');
            // Tedarikçi bazlı alım ve borç sorguları için index
            $table->index(['supplier_id', 'purchase_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
