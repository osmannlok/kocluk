<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Müşteriler tablosu.
     * balance: Müşterinin veresiye borcu. Veresiye satışta artar, ödeme alındığında azalır.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable()->unique();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Ad ile müşteri arama için index
            $table->index('name');
            // Borçlu müşteri raporları için index
            $table->index('balance');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};