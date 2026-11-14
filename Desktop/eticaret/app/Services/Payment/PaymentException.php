<?php
// app/Services/Payment/PaymentException.php

declare(strict_types=1);

namespace App\Services\Payment;

use RuntimeException;

/**
 * Ödeme sağlayıcı hataları. Mesaj kullanıcıya gösterilebilir olmalı;
 * anahtar, salt, token gibi gizli veri ASLA mesaja yazılmaz.
 */
final class PaymentException extends RuntimeException
{
}