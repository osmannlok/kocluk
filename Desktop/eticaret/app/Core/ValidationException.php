<?php // app/Core/ValidationException.php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Doğrulama hatası: alan hataları + eski girdi (oturuma flash edilir). */
class ValidationException extends RuntimeException
{
    // Eski girdiye ASLA geri yazılmayan alanlar (parola, token, kart, TC kimlik)
    private const SENSITIVE_PATTERN = '/pass|token|card|cvv|secret|tc_identity/i';

    /** @var array<string, mixed> */
    private array $old;

    /**
     * @param array<string, mixed> $errors
     * @param array<string, mixed> $old
     */
    public function __construct(private array $errors, array $old = [])
    {
        parent::__construct('Doğrulama hatası', 422);
        $this->old = self::scrub($old);
    }

    /** @return array<string, mixed> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function old(): array
    {
        return $this->old;
    }

    /**
     * @param array<string|int, mixed> $data
     * @return array<string|int, mixed>
     */
    private static function scrub(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_PATTERN, $key) === 1) {
                continue; // hassas alan oturuma yazılmaz
            }
            $out[$key] = is_array($value) ? self::scrub($value) : $value;
        }
        return $out;
    }
}