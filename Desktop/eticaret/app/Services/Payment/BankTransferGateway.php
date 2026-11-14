<?php
// app/Services/Payment/BankTransferGateway.php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Setting;

/**
 * Havale/EFT: ödeme talimatı üretir. Onay, admin dekont kontrolüyle
 * PaymentService::confirmBankTransfer üzerinden yapılır.
 */
final class BankTransferGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'bank_transfer';
    }

    public function isEnabled(): bool
    {
        return filter_var((new Setting())->get('bank_transfer.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function initiate(array $order, array $payment, array $user, array $items): array
    {
        if (!$this->isEnabled()) {
            throw new PaymentException('Havale/EFT ile ödeme şu anda kullanılamıyor.');
        }

        $settings = new Setting();
        $iban = trim((string) $settings->get('bank_transfer.iban', ''));
        if ($iban === '') {
            throw new PaymentException('Havale/EFT bilgileri henüz tanımlanmamış.');
        }

        $orderNumber = (string) $order['order_number'];

        return [
            'type'           => 'instructions',
            'method'         => 'bank_transfer',
            'order_number'   => $orderNumber,
            'amount'         => (string) $order['grand_total'],
            'deadline'       => isset($order['expires_at']) ? (string) $order['expires_at'] : null,
            'bank_name'      => (string) $settings->get('bank_transfer.bank_name', ''),
            'account_holder' => (string) $settings->get('bank_transfer.account_holder', ''),
            'iban'           => $iban,
            'note'           => (string) $settings->get('bank_transfer.note', ''),
            // Dekont eşleştirmesi için açıklamaya sipariş numarası yazdırılır.
            'description'    => 'Açıklama kısmına sipariş numaranızı yazın: ' . $orderNumber,
        ];
    }
}