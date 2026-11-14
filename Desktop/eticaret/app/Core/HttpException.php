<?php // app/Core/HttpException.php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** HTTP durum koduyla fırlatılan hata (404, 403 ...). ErrorHandler yakalar. */
class HttpException extends RuntimeException
{
    public function __construct(private int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}