<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ödemeler tablosu.
     * payment_type değerleri: cash, card, transfer, credit, refund
     * sale_id boş olabilir: müşterinin veresiye borcunu satıştan bağımsız ödemesi gibi.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_type', 30);
            $table->decimal('amount', 12, 2)->default(0);
            $table->dateTime('payment_date');
            $table->text('note')->nullable();
            $table->timestamps();

            // Tarih bazlı ödeme raporları için index
            $table->index('payment_date');
            // Nakit/kart/havale/veresiye kırılımı raporları için index
            $table->index(['payment_type', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};