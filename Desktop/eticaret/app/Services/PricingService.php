<?php
// app/Services/PricingService.php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;
use App\Models\Setting;

final class PricingService
{
    public const MAX_LINE_QUANTITY = 20;

    private CouponService $coupons;
    private ShippingCalculator $shipping;
    private Setting $settings;

    public function __construct(?CouponService $coupons = null, ?ShippingCalculator $shipping = null)
    {
        $this->coupons = $coupons ?? new CouponService();
        $this->shipping = $shipping ?? new ShippingCalculator();
        $this->settings = new Setting();
    }

    /**
     * TEK fiyat kaynağı. Fiyatlar CartItem::forCart satırlarından (DB) gelir; istemci fiyatı yok sayılır.
     * İç hesap tamsayı kuruş, çıktı "x.xx" string.
     *
     * @param array<int,array<string,mixed>> $cartLines
     */
    public function calculate(array $cartLines, ?string $couponCode, ?string $paymentMethod, ?int $userId): array
    {
        $lines = [];
        $warnings = [];
        $blocking = [];
        $subtotal = 0;
        $weight = 0;
        $tax = 0;

        foreach ($cartLines as $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $listCents = Money::toCents((string) $row['price']);
            $unitCents = Money::toCents((string) $row['final_price']);
            $isActive = (int) $row['is_active'] === 1;
            $track = (int) $row['track_stock'] === 1;
            $stock = max(0, (int) $row['stock_quantity']);
            $availableQty = $track ? min($stock, self::MAX_LINE_QUANTITY) : self::MAX_LINE_QUANTITY;
            $available = $isActive && $availableQty > 0;
            $lineCents = $unitCents * $qty;
            $label = (string) $row['product_name'] . ' (' . (string) $row['variant_name'] . ')';

            if (!$available) {
                $blocking[] = $label . ' artık satışta değil veya stokta yok. Lütfen sepetinizden çıkarın.';
            } else {
                if ($qty > $availableQty) {
                    $blocking[] = $label . ' için en fazla ' . $availableQty . ' adet sipariş verebilirsiniz.';
                }
                $subtotal += $lineCents;
                $weight += (int) $row['weight_grams'] * $qty;
                $tax += self::taxFromGross($lineCents, Money::toCents((string) $row['tax_rate']));
            }

            $lines[] = [
                'variant_id' => (int) $row['variant_id'],
                'product_name' => (string) $row['product_name'],
                'variant_name' => (string) $row['variant_name'],
                'sku' => (string) $row['sku'],
                'quantity' => $qty,
                'list_price' => Money::fromCents($listCents),
                'unit_price' => Money::fromCents($unitCents),
                'line_total' => Money::fromCents($lineCents),
                'tax_rate' => (string) $row['tax_rate'],
                'weight_grams' => (int) $row['weight_grams'],
                'available' => $available,
                'available_qty' => $availableQty,
            ];
        }

        if ($subtotal <= 0) {
            $blocking[] = 'Sepetinizde satın alınabilir ürün bulunmuyor.';
        }

        // Kupon: geçersizse sessizce düşer, uyarıya yazılır.
        $discount = 0;
        $coupon = ['code' => null, 'message' => ''];
        if ($couponCode !== null && trim($couponCode) !== '' && $subtotal > 0) {
            $result = $this->coupons->validate($couponCode, $subtotal, $userId);
            if ($result['valid']) {
                $discount = (int) $result['discount_cents'];
                $coupon = ['code' => (string) $result['coupon']['code'], 'message' => $result['message']];
            } else {
                $coupon = ['code' => null, 'message' => $result['message']];
                $warnings[] = $result['message'];
            }
        }

        $discounted = $subtotal - $discount;
        $ship = $this->shipping->calculate($weight, $discounted);

        $minAmount = $this->moneySetting('order.min_amount');
        if ($minAmount > 0 && $subtotal > 0 && $discounted < $minAmount) {
            $blocking[] = 'Minimum sipariş tutarı ' . Money::format(Money::fromCents($minAmount)) . ' olmalıdır.';
        }

        $codFee = 0;
        if ($paymentMethod !== null) {
            $methods = [
                'credit_card' => ['card.enabled', 'Kart ile ödeme şu anda kullanılamıyor.'],
                'bank_transfer' => ['bank_transfer.enabled', 'Havale/EFT ile ödeme şu anda kullanılamıyor.'],
                'cod' => ['cod.enabled', 'Kapıda ödeme şu anda kullanılamıyor.'],
            ];
            if (!isset($methods[$paymentMethod])) {
                $blocking[] = 'Geçersiz ödeme yöntemi.';
            } elseif (!(bool) $this->settings->get($methods[$paymentMethod][0], false)) {
                $blocking[] = $methods[$paymentMethod][1];
            } elseif ($paymentMethod === 'cod') {
                $codFee = $this->moneySetting('cod.fee');
            }
        }

        // KDV dahil fiyattan ayrıştırılan vergi; kupon indirimi oranında düşer (bilgi amaçlı).
        if ($discount > 0 && $subtotal > 0) {
            $tax = intdiv($tax * $discounted * 2 + $subtotal, 2 * $subtotal);
        }

        $grand = $discounted + $ship['fee_cents'] + $codFee;

        return [
            'lines' => $lines,
            'subtotal' => Money::fromCents($subtotal),
            'discount_total' => Money::fromCents($discount),
            'coupon' => $coupon,
            'shipping_total' => Money::fromCents($ship['fee_cents']),
            'shipping_is_free' => $ship['is_free'],
            'free_shipping_remaining' => Money::fromCents($ship['remaining_cents']),
            'free_shipping_threshold' => Money::fromCents($ship['threshold_cents']),
            'cod_fee' => Money::fromCents($codFee),
            'grand_total' => Money::fromCents($grand),
            'tax_total' => Money::fromCents($tax),
            'total_weight_grams' => $weight,
            'warnings' => $warnings,
            'blocking_errors' => array_values(array_unique($blocking)),
        ];
    }

    /** KDV dahil tutardan KDV'yi ayrıştırır (yarım yukarı). $rateBp: yüzde x100 (10.00 => 1000). */
    private static function taxFromGross(int $grossCents, int $rateBp): int
    {
        if ($grossCents <= 0 || $rateBp <= 0) {
            return 0;
        }
        $den = 10000 + $rateBp;
        return intdiv($grossCents * $rateBp * 2 + $den, 2 * $den);
    }

    private function moneySetting(string $key): int
    {
        $value = (string) $this->settings->get($key, '0.00');
        return preg_match('/^\d+(\.\d{1,2})?$/', $value) === 1 ? Money::toCents($value) : 0;
    }
}