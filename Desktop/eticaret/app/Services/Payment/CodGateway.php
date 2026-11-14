<?php
// app/Services/Payment/CodGateway.php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Setting;

/**
 * Kapıda ödeme: çevrimiçi adım yoktur; yalnızca ücret bilgisi döner.
 * Tahsilat, teslimatta PaymentService::markCodCollected ile işaretlenir.
 */
final class CodGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'cod';
    }

    public function isEnabled(): bool
    {
        return filter_var((new Setting())->get('cod.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function initiate(array $order, array $payment, array $user, array $items): array
    {
        if (!$this->isEnabled()) {
            throw new PaymentException('Kapıda ödeme şu anda kullanılamıyor.');
        }

        return [
            'type'    => 'none',
            'method'  => 'cod',
            'cod_fee' => (string) ($order['cod_fee'] ?? '0.00'),
        ];
    }
}