<?php
// app/Services/Payment/PayTRGateway.php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Helpers\Money;
use App\Models\Setting;
use InvalidArgumentException;

/**
 * PayTR iFrame API (1. adım: token alma). Kart verisi bu sisteme hiç uğramaz.
 * merchant_key / merchant_salt / token hiçbir yerde loglanmaz.
 */
final class PayTRGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'paytr';
    }

    public function isEnabled(): bool
    {
        return filter_var((new Setting())->get('card.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function initiate(array $order, array $payment, array $user, array $items): array
    {
        if (!$this->isEnabled()) {
            throw new PaymentException('Kart ile ödeme şu anda kullanılamıyor.');
        }

        $merchantId = (string) Config::get('paytr.merchant_id', '');
        $merchantKey = (string) Config::get('paytr.merchant_key', '');
        $merchantSalt = (string) Config::get('paytr.merchant_salt', '');
        if ($merchantId === '' || $merchantKey === '' || $merchantSalt === '') {
            Logger::error('PayTR yapılandırması eksik (merchant bilgileri).');
            throw new PaymentException('Ödeme sistemi şu anda kullanılamıyor.');
        }

        try {
            $merchantOid = (string) ($payment['merchant_oid'] ?? '');
            // PayTR merchant_oid yalnızca harf/rakam olmalı (şema CHECK'i ile uyumlu).
            if ($merchantOid === '' || preg_match('/^[A-Za-z0-9]+$/', $merchantOid) !== 1) {
                throw new PaymentException('Ödeme kaydı geçersiz.');
            }

            // Tutar HER ZAMAN sunucudaki sipariş kaydından gelir (kuruş tamsayı).
            $amountCents = Money::toCents((string) $order['grand_total']);
            if ($amountCents <= 0) {
                throw new PaymentException('Ödeme tutarı geçersiz.');
            }
            if (Money::toCents((string) $payment['amount']) !== $amountCents) {
                Logger::error('Ödeme kaydı tutarı sipariş tutarıyla uyuşmuyor.', ['order_id' => $order['id'] ?? null]);
                throw new PaymentException('Ödeme tutarı doğrulanamadı.');
            }

            $email = trim((string) ($user['email'] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new PaymentException('E-posta adresi geçersiz.');
            }

            $snapshot = $this->shippingSnapshot($order);

            $userName = trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? '')));
            if ($userName === '') {
                $userName = (string) ($snapshot['full_name'] ?? '');
            }
            $userName = $this->cleanText($userName, 60, 'Musteri');

            $addressParts = array_filter([
                (string) ($snapshot['address_line'] ?? ''),
                (string) ($snapshot['neighborhood'] ?? ''),
                (string) ($snapshot['district'] ?? ''),
                (string) ($snapshot['city'] ?? ''),
            ], static fn (string $p): bool => trim($p) !== '');
            $userAddress = $this->cleanText(implode(', ', $addressParts), 400, '');
            if ($userAddress === '') {
                throw new PaymentException('Teslimat adresi eksik.');
            }

            $phoneRaw = (string) ($snapshot['phone'] ?? ($user['phone'] ?? ''));
            $userPhone = substr((string) preg_replace('/[^0-9+]/', '', $phoneRaw), 0, 20);
            if ($userPhone === '') {
                throw new PaymentException('Telefon numarası eksik.');
            }

            $basket = $this->buildBasket($order, $items, $amountCents);

            $okBase = (string) Config::get('paytr.ok_url', '');
            $failBase = (string) Config::get('paytr.fail_url', '');
            if ($okBase === '' || $failBase === '') {
                throw new PaymentException('Ödeme sistemi şu anda kullanılamıyor.');
            }
            $orderNumber = rawurlencode((string) $order['order_number']);

            $noInstallment = (int) Config::get('paytr.no_installment', 1) === 0 ? 0 : 1;
            $maxInstallment = max(0, (int) Config::get('paytr.max_installment', 0));
            $currency = (string) Config::get('paytr.currency', 'TL');
            $testMode = (int) Config::get('paytr.test_mode', 0) === 1 ? 1 : 0;

            $fields = [
                'merchant_id'    => $merchantId,
                'user_ip'        => $this->resolveUserIp(),
                'merchant_oid'   => $merchantOid,
                'email'          => $email,
                'payment_amount' => (string) $amountCents,
                'user_basket'    => $basket,
                'no_installment' => (string) $noInstallment,
                'max_installment' => (string) $maxInstallment,
                'currency'       => $currency,
                'test_mode'      => (string) $testMode,
            ];
            $fields['paytr_token'] = $this->makeToken($fields, $merchantKey, $merchantSalt);

            // Hash'e girmeyen alanlar (lang alanı dokümanda yok, gönderilmez).
            $fields['debug_on'] = (string) ((int) Config::get('paytr.debug_on', 0) === 1 ? 1 : 0);
            $fields['user_name'] = $userName;
            $fields['user_address'] = $userAddress;
            $fields['user_phone'] = $userPhone;
            // ok/fail yalnızca müşteri yönlendirmesidir; sipariş durumunu bildirim URL'si değiştirir.
            $fields['merchant_ok_url'] = $okBase . '/' . $orderNumber;
            $fields['merchant_fail_url'] = $failBase . '/' . $orderNumber;
            $fields['timeout_limit'] = (string) max(1, (int) Config::get('paytr.timeout_limit', 25));

            return ['type' => 'iframe', 'token' => $this->requestToken($fields)];
        } catch (InvalidArgumentException $e) {
            throw new PaymentException('Sipariş tutar bilgisi geçersiz.', 0, $e);
        }
    }

    /**
     * Bildirim (2. adım) hash doğrulaması. Zamanlama saldırılarına karşı hash_equals.
     *
     * @param array<string,mixed> $post
     */
    public function verifyCallbackHash(array $post): bool
    {
        foreach (['merchant_oid', 'status', 'total_amount', 'hash'] as $key) {
            if (!isset($post[$key]) || !is_string($post[$key])) {
                return false;
            }
        }

        $merchantKey = (string) Config::get('paytr.merchant_key', '');
        $merchantSalt = (string) Config::get('paytr.merchant_salt', '');
        if ($merchantKey === '' || $merchantSalt === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            $post['merchant_oid'] . $merchantSalt . $post['status'] . $post['total_amount'],
            $merchantKey,
            true
        ));

        return hash_equals($expected, $post['hash']);
    }

    /**
     * paytr_token: hash_str sırası dokümandaki gibidir (user_basket dahil).
     *
     * @param array<string,string> $f
     */
    private function makeToken(array $f, string $merchantKey, string $merchantSalt): string
    {
        $hashStr = $f['merchant_id'] . $f['user_ip'] . $f['merchant_oid'] . $f['email']
            . $f['payment_amount'] . $f['user_basket'] . $f['no_installment']
            . $f['max_installment'] . $f['currency'] . $f['test_mode'];

        return base64_encode(hash_hmac('sha256', $hashStr . $merchantSalt, $merchantKey, true));
    }

    /**
     * user_basket toplamı HER ZAMAN payment_amount'a eşittir.
     * Kupon/indirim varsa tek satırlık özet sepet; yoksa kalemler + kargo + kapıda ücret.
     *
     * @param array<string,mixed>            $order
     * @param array<int,array<string,mixed>> $items
     */
    private function buildBasket(array $order, array $items, int $amountCents): string
    {
        $discountCents = Money::toCents((string) ($order['discount_total'] ?? '0'));
        $hasDiscount = $discountCents > 0 || trim((string) ($order['coupon_code'] ?? '')) !== '';

        if ($hasDiscount) {
            $lines = [[
                $this->cleanText('Siparis ' . (string) $order['order_number'], 100, 'Siparis'),
                Money::fromCents($amountCents),
                1,
            ]];
        } else {
            $lines = [];
            $sumCents = 0;
            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $unitCents = Money::toCents((string) ($item['unit_price'] ?? '0'));
                if ($qty < 1 || $unitCents < 0) {
                    throw new PaymentException('Sepet kalemi geçersiz.');
                }
                $name = trim((string) ($item['product_name'] ?? '') . ' ' . (string) ($item['variant_name'] ?? ''));
                $lines[] = [$this->cleanText($name, 100, 'Urun'), Money::fromCents($unitCents), $qty];
                $sumCents += $unitCents * $qty;
            }

            $shippingCents = Money::toCents((string) ($order['shipping_total'] ?? '0'));
            if ($shippingCents > 0) {
                $lines[] = ['Kargo', Money::fromCents($shippingCents), 1];
                $sumCents += $shippingCents;
            }

            $codCents = Money::toCents((string) ($order['cod_fee'] ?? '0'));
            if ($codCents > 0) {
                $lines[] = ['Kapıda ödeme ücreti', Money::fromCents($codCents), 1];
                $sumCents += $codCents;
            }

            // Kuruş bazında tutarlılık kontrolü: eşleşmezse PayTR'ye hiç gitmeyiz.
            if ($lines === [] || $sumCents !== $amountCents) {
                Logger::error('PayTR sepet toplamı ödeme tutarıyla eşleşmiyor.', ['order_id' => $order['id'] ?? null]);
                throw new PaymentException('Sepet toplamı ödeme tutarıyla eşleşmiyor.');
            }
        }

        $json = json_encode($lines);
        if ($json === false) {
            throw new PaymentException('Sepet bilgisi oluşturulamadı.');
        }

        return base64_encode($json);
    }

    /**
     * @param array<string,string> $fields
     */
    private function requestToken(array $fields): string
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new PaymentException('Ödeme sistemine bağlanılamadı.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => (string) Config::get('paytr.api_url', ''),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            // TLS doğrulaması AÇIK (güvenlik kuralı 22).
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch);

        if ($body === false || $errNo !== 0) {
            Logger::error('PayTR token isteği başarısız (bağlantı).', ['errno' => $errNo, 'error' => $err]);
            throw new PaymentException('Ödeme sistemine bağlanılamadı. Lütfen tekrar deneyin.');
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            Logger::warning('PayTR token isteği reddedildi.', [
                'http'   => $http,
                'reason' => is_array($data) ? mb_substr((string) ($data['reason'] ?? ''), 0, 200) : 'invalid_json',
            ]);
            throw new PaymentException('Ödeme sayfası oluşturulamadı. Lütfen tekrar deneyin.');
        }

        $token = $data['token'] ?? null;
        // Token iframe src'sine girer; beklenmeyen karakter varsa reddet.
        if (!is_string($token) || $token === '' || strlen($token) > 255 || preg_match('/^[A-Za-z0-9_-]+$/', $token) !== 1) {
            Logger::warning('PayTR token biçimi geçersiz.', ['http' => $http]);
            throw new PaymentException('Ödeme sayfası oluşturulamadı. Lütfen tekrar deneyin.');
        }

        return $token;
    }

    /**
     * Yerelde (APP_ENV=local) PAYTR_USER_IP doluysa o kullanılır; üretimde override yok sayılır.
     */
    private function resolveUserIp(): string
    {
        $override = trim((string) Config::get('paytr.user_ip_override', ''));
        if (
            $override !== ''
            && (string) Config::get('app.env', 'production') === 'local'
            && filter_var($override, FILTER_VALIDATE_IP) !== false
        ) {
            return $override;
        }

        return Request::capture()->ip();
    }

    /**
     * @param array<string,mixed> $order
     * @return array<string,mixed>
     */
    private function shippingSnapshot(array $order): array
    {
        $raw = $order['shipping_address'] ?? '';
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Kontrol karakterlerini temizler, boşlukları sadeleştirir, uzunluğu sınırlar.
     */
    private function cleanText(string $text, int $max, string $fallback): string
    {
        $s = mb_scrub($text, 'UTF-8');
        $s = preg_replace('/\p{Cc}+/u', ' ', $s) ?? '';
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        $s = mb_substr($s, 0, $max, 'UTF-8');

        return $s === '' ? $fallback : $s;
    }
}