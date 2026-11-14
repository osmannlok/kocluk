<?php
// app/Core/Csrf.php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        $t = Session::get(self::KEY);
        if (!is_string($t) || strlen($t) !== 64) {
            $t = bin2hex(random_bytes(32));
            Session::put(self::KEY, $t);
        }
        return $t;
    }

    // hash_equals: zamanlama saldırısına karşı sabit süreli karşılaştırma.
    public static function validate(?string $t): bool
    {
        if ($t === null || $t === '') {
            return false;
        }
        return hash_equals(self::token(), $t);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }
}