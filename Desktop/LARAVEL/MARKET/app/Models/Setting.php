<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /* ---------------------------------------------------------------
     | Yardımcılar
     |---------------------------------------------------------------*/

    /**
     * Ayar değerini anahtara göre getirir.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * Ayar değerini oluşturur veya günceller.
     */
    public static function setValue(string $key, mixed $value): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}