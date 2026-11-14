<?php
// app/Services/CartService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use RuntimeException;

final class CartService
{
    public const COOKIE_NAME = 'ym_cart';
    private const COOKIE_TTL = 2592000; // 30 gün
    private const SESSION_COUPON = 'cart.coupon';

    private Cart $carts;
    private CartItem $items;
    private ProductVariant $variants;
    private ?int $cartId = null;
    private bool $mergeChecked = false;

    public function __construct()
    {
        $this->carts = new Cart();
        $this->items = new CartItem();
        $this->variants = new ProductVariant();
    }

    public function currentCartId(bool $create = false): ?int
    {
        if ($this->cartId !== null) {
            return $this->cartId;
        }

        if (Auth::check()) {
            $userId = (int) Auth::id();
            $this->mergeGuestCart($userId);
            $cart = $this->carts->findByUserId($userId);
            if ($cart === null) {
                if (!$create) {
                    return null;
                }
                $id = $this->carts->createForUser($userId);
            } else {
                $id = (int) $cart['id'];
            }
            if ($create) {
                $this->carts->touch($id, $this->expiry());
            }
            return $this->cartId = $id;
        }

        $raw = $this->validCookie();
        if ($raw !== null) {
            $cart = $this->carts->findByGuestHash(hash('sha256', $raw));
            if ($cart !== null && strtotime((string) $cart['expires_at']) > time()) {
                $id = (int) $cart['id'];
                if ($create) {
                    $this->carts->touch($id, $this->expiry());
                }
                return $this->cartId = $id;
            }
        }
        if (!$create) {
            return null;
        }

        $raw = $this->issueCookie();
        return $this->cartId = $this->carts->createForGuest(hash('sha256', $raw), $this->expiry());
    }

    /** @return array{ok:bool,message:string,count:int} */
    public function add(int $variantId, int $qty): array
    {
        if ($variantId < 1 || $qty < 1 || $qty > PricingService::MAX_LINE_QUANTITY) {
            return $this->result(false, 'Geçersiz adet.');
        }

        $variant = $this->variants->findActiveMany([$variantId])[$variantId] ?? null;
        if ($variant === null) {
            return $this->result(false, 'Ürün bulunamadı veya satışta değil.');
        }
        $max = $this->maxFor($variant);
        if ($max <= 0) {
            return $this->result(false, 'Bu ürün şu anda stokta yok.');
        }

        $cartId = (int) $this->currentCartId(true);
        $existing = $this->quantities($cartId)[$variantId] ?? 0;
        $target = $existing + $qty;
        $message = 'Ürün sepete eklendi.';

        if ($target > $max) {
            if ($existing >= $max) {
                return $this->result(false, 'Bu üründen en fazla ' . $max . ' adet ekleyebilirsiniz.');
            }
            $target = $max;
            $message = 'Stok/limit nedeniyle adet ' . $max . ' olarak ayarlandı.';
        }

        if ($existing > 0) {
            $this->items->setQuantity($cartId, $variantId, $target);
        } else {
            $this->items->upsertAdd($cartId, $variantId, $target);
        }
        return $this->result(true, $message);
    }

    /** @return array{ok:bool,message:string,count:int} */
    public function setQuantity(int $variantId, int $qty): array
    {
        if ($qty <= 0) {
            return $this->remove($variantId);
        }
        $cartId = $this->currentCartId(false);
        if ($cartId === null) {
            return $this->result(false, 'Sepetiniz boş.');
        }

        $line = null;
        foreach ($this->items->forCart($cartId) as $row) {
            if ((int) $row['variant_id'] === $variantId) {
                $line = $row;
                break;
            }
        }
        if ($line === null) {
            return $this->result(false, 'Ürün sepetinizde bulunamadı.');
        }
        if ((int) $line['is_active'] !== 1) {
            return $this->result(false, 'Bu ürün artık satışta değil. Lütfen sepetinizden çıkarın.');
        }

        $max = $this->maxFor($line);
        if ($max <= 0) {
            return $this->result(false, 'Bu ürün şu anda stokta yok.');
        }

        $target = min($qty, $max);
        $this->items->setQuantity($cartId, $variantId, $target);
        $message = $target < $qty ? 'Stok/limit nedeniyle adet ' . $target . ' olarak ayarlandı.' : 'Sepet güncellendi.';
        return $this->result(true, $message);
    }

    /** @return array{ok:bool,message:string,count:int} */
    public function remove(int $variantId): array
    {
        $cartId = $this->currentCartId(false);
        if ($cartId !== null) {
            $this->items->remove($cartId, $variantId);
        }
        return $this->result(true, 'Ürün sepetten çıkarıldı.');
    }

    public function clear(): void
    {
        $cartId = $this->currentCartId(false);
        if ($cartId !== null) {
            $this->items->clear($cartId);
        }
        $this->removeCoupon();
    }

    /** @return array<int,array<string,mixed>> */
    public function lines(): array
    {
        $cartId = $this->currentCartId(false);
        return $cartId === null ? [] : $this->items->forCart($cartId);
    }

    public function count(): int
    {
        $cartId = $this->currentCartId(false);
        return $cartId === null ? 0 : $this->items->countItems($cartId);
    }

    public function couponCode(): ?string
    {
        $code = Session::get(self::SESSION_COUPON);
        return is_string($code) && $code !== '' ? $code : null;
    }

    /** @return array{ok:bool,message:string} */
    public function applyCoupon(string $code): array
    {
        $code = mb_strtoupper(trim($code), 'UTF-8');
        $lines = $this->lines();
        if ($lines === []) {
            return ['ok' => false, 'message' => 'Kupon uygulamak için sepetinizde ürün olmalı.'];
        }

        // Ara toplam PricingService'ten alınır: tek fiyat kaynağı.
        $pricing = (new PricingService())->calculate($lines, null, null, Auth::id());
        $subtotalCents = \App\Helpers\Money::toCents($pricing['subtotal']);

        $result = (new CouponService())->validate($code, $subtotalCents, Auth::id());
        if (!$result['valid']) {
            return ['ok' => false, 'message' => $result['message']];
        }

        Session::put(self::SESSION_COUPON, (string) $result['coupon']['code']);
        return ['ok' => true, 'message' => $result['message']];
    }

    public function removeCoupon(): void
    {
        Session::forget(self::SESSION_COUPON);
    }

    /**
     * Tembel birleştirme: girişli kullanıcı + geçerli misafir çerezi varsa ilk erişimde
     * misafir kalemleri kullanıcı sepetine taşınır (UNIQUE user_id nedeniyle assignToUser kullanılmaz).
     */
    private function mergeGuestCart(int $userId): void
    {
        if ($this->mergeChecked) {
            return;
        }
        $this->mergeChecked = true;

        $raw = $this->validCookie();
        if ($raw === null) {
            return;
        }
        $guest = $this->carts->findByGuestHash(hash('sha256', $raw));
        if ($guest === null) {
            $this->forgetCookie();
            return;
        }

        Database::instance()->transaction(function () use ($guest, $userId): void {
            $userCart = $this->carts->findByUserId($userId);
            $userCartId = $userCart !== null ? (int) $userCart['id'] : $this->carts->createForUser($userId);
            $existing = $this->quantities($userCartId);

            foreach ($this->items->forCart((int) $guest['id']) as $row) {
                if ((int) $row['is_active'] !== 1) {
                    continue;
                }
                $variantId = (int) $row['variant_id'];
                $max = $this->maxFor($row);
                if ($max <= 0) {
                    continue;
                }
                $target = min(($existing[$variantId] ?? 0) + (int) $row['quantity'], $max);
                if (isset($existing[$variantId])) {
                    $this->items->setQuantity($userCartId, $variantId, $target);
                } else {
                    $this->items->upsertAdd($userCartId, $variantId, $target);
                }
            }

            $this->carts->touch($userCartId, $this->expiry());
            $this->carts->deleteById((int) $guest['id']);
        });

        $this->forgetCookie();
    }

    /** Adet üst sınırı: min(20, stok); stok takibi yoksa 20. */
    private function maxFor(array $row): int
    {
        if ((int) $row['track_stock'] === 1) {
            return max(0, min(PricingService::MAX_LINE_QUANTITY, (int) $row['stock_quantity']));
        }
        return PricingService::MAX_LINE_QUANTITY;
    }

    /** @return array<int,int> variant_id => adet */
    private function quantities(int $cartId): array
    {
        $map = [];
        foreach ($this->items->forCart($cartId) as $row) {
            $map[(int) $row['variant_id']] = (int) $row['quantity'];
        }
        return $map;
    }

    /** @return array{ok:bool,message:string,count:int} */
    private function result(bool $ok, string $message): array
    {
        return ['ok' => $ok, 'message' => $message, 'count' => $this->count()];
    }

    private function expiry(): string
    {
        return date('Y-m-d H:i:s', time() + self::COOKIE_TTL);
    }

    private function secretKey(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY tanımlı değil.');
        }
        return $key;
    }

    /** İmzası doğrulanmış ham çerez değeri; sahte/bozuk çerez null döner (HMAC: sunucu dışında üretilemez). */
    private function validCookie(): ?string
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!is_string($raw)) {
            return null;
        }
        $parts = explode('.', $raw);
        if (count($parts) !== 2 || preg_match('/^[a-f0-9]{64}$/', $parts[0]) !== 1 || preg_match('/^[a-f0-9]{64}$/', $parts[1]) !== 1) {
            return null;
        }
        $expected = hash_hmac('sha256', $parts[0], $this->secretKey());
        return hash_equals($expected, $parts[1]) ? $raw : null;
    }

    private function issueCookie(): string
    {
        $token = bin2hex(random_bytes(32));
        $raw = $token . '.' . hash_hmac('sha256', $token, $this->secretKey());
        $this->setCookie($raw, time() + self::COOKIE_TTL);
        $_COOKIE[self::COOKIE_NAME] = $raw;
        return $raw;
    }

    private function forgetCookie(): void
    {
        $this->setCookie('', time() - 3600);
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    private function setCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || str_starts_with((string) config('app.url'), 'https://');
        setcookie(self::COOKIE_NAME, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}