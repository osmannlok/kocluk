<?php
// app/Services/CouponService.php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;
use App\Models\Coupon;
use App\Models\CouponUsage;

final class CouponService
{
    // Kupon varlığını sızdırmamak için çoğu hatada aynı genel mesaj döner.
    private const MSG_GENERIC = 'Kupon kodu geçersiz veya şu anda kullanılamıyor.';

    private Coupon $coupons;
    private CouponUsage $usages;

    public function __construct()
    {
        $this->coupons = new Coupon();
        $this->usages = new CouponUsage();
    }

    /**
     * Kuponu doğrular ve indirimi hesaplar. Kullanım sayacını ARTIRMAZ
     * (artırma sipariş transaction'ında yapılır).
     *
     * @return array{valid:bool, coupon?:array, discount_cents?:int, message:string}
     */
    public function validate(string $code, int $subtotalCents, ?int $userId): array
    {
        $code = mb_strtoupper(trim($code), 'UTF-8');
        if (preg_match('/^[A-Z0-9_-]{3,40}$/', $code) !== 1) {
            return $this->invalid(self::MSG_GENERIC);
        }
        if ($subtotalCents <= 0) {
            return $this->invalid('Kupon uygulamak için sepetinizde ürün olmalı.');
        }

        $coupon = $this->coupons->findByCode($code);
        if ($coupon === null || !empty($coupon['deleted_at']) || (int) $coupon['is_active'] !== 1) {
            return $this->invalid(self::MSG_GENERIC);
        }

        $now = time();
        if (!empty($coupon['starts_at']) && strtotime((string) $coupon['starts_at']) > $now) {
            return $this->invalid(self::MSG_GENERIC);
        }
        if (!empty($coupon['ends_at']) && strtotime((string) $coupon['ends_at']) < $now) {
            return $this->invalid(self::MSG_GENERIC);
        }

        if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
            return $this->invalid(self::MSG_GENERIC);
        }

        if ($coupon['usage_per_user'] !== null) {
            if ($userId === null) {
                return $this->invalid('Bu kuponu kullanmak için giriş yapmalısınız.');
            }
            if ($this->usages->countByUser((int) $coupon['id'], $userId) >= (int) $coupon['usage_per_user']) {
                return $this->invalid(self::MSG_GENERIC);
            }
        }

        $minCents = Money::toCents((string) $coupon['min_cart_total']);
        if ($minCents > 0 && $subtotalCents < $minCents) {
            return $this->invalid(
                'Bu kupon için sepet tutarınız en az ' . Money::format(Money::fromCents($minCents)) . ' olmalıdır.'
            );
        }

        $discount = $this->discountCents($coupon, $subtotalCents);
        if ($discount <= 0) {
            return $this->invalid(self::MSG_GENERIC);
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount_cents' => $discount,
            'message' => 'Kupon uygulandı.',
        ];
    }

    private function discountCents(array $coupon, int $subtotalCents): int
    {
        if ((string) $coupon['discount_type'] === 'percent') {
            $discount = Money::percent($subtotalCents, (string) $coupon['discount_value']);
            if ($coupon['max_discount_amount'] !== null) {
                $cap = Money::toCents((string) $coupon['max_discount_amount']);
                if ($cap > 0) {
                    $discount = min($discount, $cap);
                }
            }
        } else {
            $discount = Money::toCents((string) $coupon['discount_value']);
        }

        // İndirim ara toplamı asla aşamaz.
        return max(0, min($discount, $subtotalCents));
    }

    /** @return array{valid:bool, message:string} */
    private function invalid(string $message): array
    {
        return ['valid' => false, 'message' => $message];
    }
}