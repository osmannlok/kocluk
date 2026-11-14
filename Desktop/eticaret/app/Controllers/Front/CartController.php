<?php
// app/Controllers/Front/CartController.php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Money;
use App\Services\CartService;
use App\Services\PricingService;

final class CartController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->rememberCheckoutTarget();

        return $this->view('front.cart.index', [
            'title' => 'Sepetim',
            'metaDescription' => 'Alışveriş sepetiniz.',
            'canonical' => url('/sepet'),
            'bodyClass' => 'page-cart',
            'scripts' => ['cart.js'],
            'summary' => $this->buildSummary(),
            'checkoutHref' => Auth::check() ? '/odeme' : '/giris',
        ]);
    }

    public function summary(Request $request): Response
    {
        $this->rememberCheckoutTarget();
        return $this->ok($this->buildSummary());
    }

    public function add(Request $request): Response
    {
        $data = $this->payload($request);
        $variantId = $this->intField($data, 'variant_id');
        $qty = $this->intField($data, 'quantity');
        if ($variantId === null || $variantId < 1 || $qty === null || $qty < 1) {
            return $this->invalid(['variant_id' => 'Geçerli bir ürün ve adet seçin.']);
        }

        $result = (new CartService())->add($variantId, $qty);
        return $this->respond($result);
    }

    public function update(Request $request): Response
    {
        $data = $this->payload($request);
        $variantId = $this->intField($data, 'variant_id');
        $qty = $this->intField($data, 'quantity');
        // adet 0 = kalemi sil; üst sınır serviste uygulanır.
        if ($variantId === null || $variantId < 1 || $qty === null || $qty < 0 || $qty > 99) {
            return $this->invalid(['quantity' => 'Geçerli bir adet girin.']);
        }

        return $this->respond((new CartService())->setQuantity($variantId, $qty));
    }

    public function remove(Request $request): Response
    {
        $variantId = $this->intField($this->payload($request), 'variant_id');
        if ($variantId === null || $variantId < 1) {
            return $this->invalid(['variant_id' => 'Geçerli bir ürün seçin.']);
        }

        return $this->respond((new CartService())->remove($variantId));
    }

    public function applyCoupon(Request $request): Response
    {
        $data = $this->payload($request);
        $code = isset($data['code']) && is_string($data['code']) ? trim($data['code']) : '';
        if ($code === '' || mb_strlen($code) > 40) {
            return $this->invalid(['code' => 'Kupon kodunu girin.']);
        }

        $result = (new CartService())->applyCoupon($code);
        if (!$result['ok']) {
            return $this->fail('coupon_invalid', $result['message'], 422);
        }
        return $this->ok($this->buildSummary() + ['message' => $result['message']]);
    }

    public function removeCoupon(Request $request): Response
    {
        (new CartService())->removeCoupon();
        return $this->ok($this->buildSummary() + ['message' => 'Kupon kaldırıldı.']);
    }

    /** @param array{ok:bool,message:string,count:int} $result */
    private function respond(array $result): Response
    {
        if (!$result['ok']) {
            return $this->fail('cart_error', $result['message'], 422);
        }
        return $this->ok($this->buildSummary() + ['message' => $result['message']]);
    }

    /** Güncel sepet özeti: fiyatlar yalnızca PricingService'ten (sunucu) gelir. */
    private function buildSummary(): array
    {
        $cart = new CartService();
        $lines = $cart->lines();
        $code = $cart->couponCode();
        $pricing = (new PricingService())->calculate($lines, $code, null, Auth::id());

        // Kupon artık geçerli değilse oturumdan düşür.
        if ($code !== null && $pricing['coupon']['code'] === null) {
            $cart->removeCoupon();
        }

        $meta = [];
        foreach ($lines as $row) {
            $meta[(int) $row['variant_id']] = [
                'slug' => (string) ($row['slug'] ?? ''),
                'image_path' => $row['image_path'] !== null ? (string) $row['image_path'] : null,
            ];
        }
        foreach ($pricing['lines'] as &$line) {
            $line['slug'] = $meta[$line['variant_id']]['slug'] ?? '';
            $line['image_path'] = $meta[$line['variant_id']]['image_path'] ?? null;
        }
        unset($line);

        $thresholdCents = Money::toCents($pricing['free_shipping_threshold']);
        $payable = Money::toCents($pricing['subtotal']) - Money::toCents($pricing['discount_total']);
        $percent = $thresholdCents > 0 ? min(100, max(0, intdiv($payable * 100, $thresholdCents))) : 0;

        return [
            'count' => $cart->count(),
            'lines' => $pricing['lines'],
            'subtotal' => $pricing['subtotal'],
            'discount_total' => $pricing['discount_total'],
            'coupon' => $pricing['coupon'],
            'shipping_total' => $pricing['shipping_total'],
            'shipping_is_free' => $pricing['shipping_is_free'],
            'cod_fee' => $pricing['cod_fee'],
            'grand_total' => $pricing['grand_total'],
            'tax_total' => $pricing['tax_total'],
            'free_shipping' => [
                'threshold' => $pricing['free_shipping_threshold'],
                'remaining' => $pricing['free_shipping_remaining'],
                'percent' => $percent,
                'is_free' => $pricing['shipping_is_free'],
            ],
            'warnings' => $pricing['warnings'],
            'blocking_errors' => $pricing['blocking_errors'],
        ];
    }

    /** Girişsiz kullanıcı "Ödemeye geç"e basarsa giriş sonrası /odeme'ye döner. */
    private function rememberCheckoutTarget(): void
    {
        if (!Auth::check()) {
            Session::put('intended_url', '/odeme');
        }
    }

    /** JSON gövde (AJAX) veya form verisi. */
    private function payload(Request $request): array
    {
        if (str_contains(strtolower((string) $request->header('Content-Type')), 'json')) {
            return $request->json();
        }
        return $request->all();
    }

    private function intField(array $data, string $key): ?int
    {
        if (!array_key_exists($key, $data) || is_array($data[$key]) || is_bool($data[$key])) {
            return null;
        }
        $value = filter_var($data[$key], FILTER_VALIDATE_INT);
        return $value === false ? null : (int) $value;
    }

    /** @param array<string,string> $errors */
    private function invalid(array $errors): Response
    {
        return $this->fail('validation', 'Geçersiz istek.', 422, $errors);
    }
}