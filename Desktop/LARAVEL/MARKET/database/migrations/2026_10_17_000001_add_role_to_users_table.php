<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users tablosuna rol, telefon ve aktiflik alanlarını ekler.
     * Roller: admin, cashier, warehouse, accountant
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('cashier')->after('password');
            $table->string('phone', 30)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');

            // Rol bazlı sorgular ve kullanıcı listeleri için index
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};