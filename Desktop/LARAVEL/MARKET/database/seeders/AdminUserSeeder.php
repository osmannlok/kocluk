<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Varsayılan yönetici kullanıcısını oluşturur.
     * Seeder birden fazla kez çalıştırılırsa kayıt çoğalmaz (updateOrCreate).
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@market.local'],
            [
                'name' => 'Yönetici',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}