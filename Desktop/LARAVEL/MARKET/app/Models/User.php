<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Kullanıcı rolleri
    public const ROLE_ADMIN = 'admin';
    public const ROLE_CASHIER = 'cashier';
    public const ROLE_WAREHOUSE = 'warehouse';
    public const ROLE_ACCOUNTANT = 'accountant';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_CASHIER,
        self::ROLE_WAREHOUSE,
        self::ROLE_ACCOUNTANT,
    ];

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
        'email_verified_at',
    ];

    /**
     * Serileştirmede gizlenecek alanlar.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Alan tipi dönüşümleri.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Kullanıcının belirtilen rollerden birine sahip olup olmadığını kontrol eder.
     */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}