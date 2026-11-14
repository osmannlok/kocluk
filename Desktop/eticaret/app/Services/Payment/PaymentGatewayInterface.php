<?php
// app/Services/Payment/PaymentGatewayInterface.php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Ödeme yöntemi sağlayıcı sözleşmesi (PayTR, havale/EFT, kapıda ödeme).
 * Yeni bir ödeme yöntemi eklemek için bu arayüzü uygulamak yeterlidir.
 */
interface PaymentGatewayInterface
{
    /**
     * Ödemeyi başlatır ve yöntemin türüne göre sonuç döner:
     *  - ['type' => 'iframe', 'token' => string]
     *      PayTR iFrame token'ı; sayfada iframe ile gösterilir.
     *  - ['type' => 'instructions', ...]
     *      Havale/EFT talimatı (bank_name, account_holder, iban, note, deadline, description...).
     *  - ['type' => 'none', ...]
     *      Ek adım gerektirmeyen yöntem (kapıda ödeme: cod_fee).
     *
     * Hata durumunda PaymentException fırlatır; mesajda gizli veri (anahtar, salt, token) bulunmaz.
     *
     * @param array<string,mixed>            $order   orders satırı (grand_total, order_number, ...)
     * @param array<string,mixed>            $payment payments satırı (merchant_oid, ...)
     * @param array<string,mixed>            $user    users satırı (email, first_name, last_name, ...)
     * @param array<int,array<string,mixed>> $items   order_items satırları
     *
     * @return array<string,mixed>
     */
    public function initiate(array $order, array $payment, array $user, array $items): array;

    /**
     * Yöntemin kısa adı (payments.provider ile uyumlu: paytr | bank_transfer | cod).
     */
    public function name(): string;

    /**
     * Yöntem ayarlardan (settings) açık mı?
     */
    public function isEnabled(): bool;
}