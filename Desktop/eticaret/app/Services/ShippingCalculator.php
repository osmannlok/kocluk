<?php
// app/Services/ShippingCalculator.php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Money;
use App\Models\Setting;
use App\Models\ShippingRate;

final class ShippingCalculator
{
    private Setting $settings;
    private ShippingRate $rates;

    public function __construct()
    {
        $this->settings = new Setting();
        $this->rates = new ShippingRate();
    }

    /**
     * Kargo ücretini sunucuda hesaplar (istemci değeri asla kullanılmaz).
     *
     * @return array{fee_cents:int, is_free:bool, threshold_cents:int, remaining_cents:int, label:string}
     */
    public function calculate(int $totalWeightGrams, int $discountedSubtotalCents): array
    {
        $thresholdCents = $this->thresholdCents();
        $remaining = $thresholdCents > 0 ? max(0, $thresholdCents - $discountedSubtotalCents) : 0;

        // Boş sepette kargo yok.
        if ($totalWeightGrams <= 0 && $discountedSubtotalCents <= 0) {
            return [
                'fee_cents' => 0,
                'is_free' => false,
                'threshold_cents' => $thresholdCents,
                'remaining_cents' => $remaining,
                'label' => 'Kargo',
            ];
        }

        // Eşik 0 veya negatifse ücretsiz kargo özelliği kapalı sayılır.
        if ($thresholdCents > 0 && $discountedSubtotalCents >= $thresholdCents) {
            return [
                'fee_cents' => 0,
                'is_free' => true,
                'threshold_cents' => $thresholdCents,
                'remaining_cents' => 0,
                'label' => 'Ücretsiz kargo',
            ];
        }

        return [
            'fee_cents' => $this->feeForWeight(max(0, $totalWeightGrams)),
            'is_free' => false,
            'threshold_cents' => $thresholdCents,
            'remaining_cents' => $remaining,
            'label' => 'Kargo',
        ];
    }

    private function thresholdCents(): int
    {
        $value = $this->settings->get('shipping.free_threshold', '0.00');
        if (!is_string($value) && !is_int($value)) {
            return 0;
        }
        $value = (string) $value;
        if (preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
            return 0;
        }
        return Money::toCents($value);
    }

    private function feeForWeight(int $grams): int
    {
        $price = $this->rates->priceForWeight($grams);
        if ($price !== null) {
            return Money::toCents($price);
        }

        // Tarifede aralık bulunamadıysa en yüksek ağırlık bandı uygulanır.
        $topBand = null;
        foreach ($this->rates->activeAll() as $rate) {
            if ($topBand === null || (int) $rate['max_weight_grams'] > (int) $topBand['max_weight_grams']) {
                $topBand = $rate;
            }
        }
        return $topBand === null ? 0 : Money::toCents((string) $topBand['price']);
    }
}