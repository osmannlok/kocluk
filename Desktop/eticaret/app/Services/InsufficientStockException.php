<?php // app/Services/InsufficientStockException.php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Stok yetersiz olduğunda fırlatılır; hangi varyantın yetmediğini taşır.
 */
final class InsufficientStockException extends RuntimeException
{
    public function __construct(private readonly int $variantId, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Yeterli stok bulunmuyor.');
    }

    public function getVariantId(): int
    {
        return $this->variantId;
    }
}