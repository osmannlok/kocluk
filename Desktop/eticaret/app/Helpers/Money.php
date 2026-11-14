<?php // app/Helpers/Money.php
declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Para yardımcısı: tüm hesap TAMSAYI KURUŞ ile yapılır, float ile para hesabı yoktur.
 */
final class Money
{
    // DECIMAL(12,2) üst sınırı: 9999999999.99 TL = 999999999999 kuruş
    private const MAX_CENTS = 999999999999;

    private function __construct()
    {
    }

    /** "12.5", 12.5, 1250 (=1250 TL) gibi TL tutarını kuruşa çevirir; yarım yukarı yuvarlar. */
    public static function toCents(string|int|float $amount): int
    {
        if (is_int($amount)) {
            if ($amount < 0 || $amount > intdiv(self::MAX_CENTS, 100)) {
                throw new InvalidArgumentException('Geçersiz tutar.');
            }
            return $amount * 100;
        }

        if (is_float($amount)) {
            if (!is_finite($amount) || $amount < 0) {
                throw new InvalidArgumentException('Geçersiz tutar.');
            }
            // Float ikilik hatasını (0.1+0.2) 6 hane yazdırarak giderir; yerel ayardan bağımsız ('F')
            $amount = sprintf('%.6F', $amount);
        }

        $amount = trim($amount);
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,12}))?$/D', $amount, $m) !== 1) {
            throw new InvalidArgumentException('Geçersiz tutar.');
        }

        $frac = str_pad($m[2] ?? '', 3, '0');
        $cents = ((int) $m[1]) * 100 + (int) substr($frac, 0, 2);
        if ($frac[2] >= '5') { // yarım yukarı
            $cents++;
        }
        if ($cents > self::MAX_CENTS) {
            throw new InvalidArgumentException('Tutar sınırı aşıldı.');
        }
        return $cents;
    }

    /** 123450 -> "1234.50" */
    public static function fromCents(int $cents): string
    {
        if ($cents < 0 || $cents > self::MAX_CENTS) {
            throw new InvalidArgumentException('Geçersiz kuruş değeri.');
        }
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    /** Kuruşun yüzdesi (oran "10.00" gibi, en çok 2 ondalık, 0-100); yarım yukarı yuvarlar. */
    public static function percent(int $cents, string $rate): int
    {
        if ($cents < 0 || preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/D', $rate, $m) !== 1) {
            throw new InvalidArgumentException('Geçersiz yüzde hesabı.');
        }
        // Oran yüzde'nin yüzde biri cinsinden tamsayı (10.00 -> 1000)
        $basisPoints = ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');
        if ($basisPoints > 10000) {
            throw new InvalidArgumentException('Oran 100\'ü aşamaz.');
        }
        return intdiv($cents * $basisPoints + 5000, 10000);
    }

    /** "1234.5" -> "1.234,50 ₺" */
    public static function format(string $decimal): string
    {
        [$int, $frac] = explode('.', self::fromCents(self::toCents($decimal)));
        $int = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int);
        return $int . ',' . $frac . ' ₺';
    }
}