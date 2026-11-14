<?php // app/Core/Logger.php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Günlük dosya logger'ı: storage/logs/YYYY-MM-DD.log. Yazma hatası uygulamayı düşürmez.
 */
final class Logger
{
    // Hassas anahtar adları maskelenir (parola, token, kart, cvv, gizli, salt, anahtar, hash, tc kimlik)
    private const SENSITIVE_PATTERN = '/password|token|card|cvv|secret|salt|key|hash|(^|[_.\-])tc([_.\-]|$)/i';
    private const MAX_DEPTH = 5;

    private function __construct()
    {
    }

    /** @param array<string, mixed> $ctx */
    public static function error(string $msg, array $ctx = []): void
    {
        self::write('ERROR', $msg, $ctx);
    }

    /** @param array<string, mixed> $ctx */
    public static function warning(string $msg, array $ctx = []): void
    {
        self::write('WARNING', $msg, $ctx);
    }

    /** @param array<string, mixed> $ctx */
    public static function info(string $msg, array $ctx = []): void
    {
        self::write('INFO', $msg, $ctx);
    }

    /** @param array<string, mixed> $ctx */
    private static function write(string $level, string $msg, array $ctx): void
    {
        try {
            $dir = Config::basePath('storage/logs');
            if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
                return;
            }

            // Satır sonları temizlenir: log enjeksiyonu (sahte satır) engellenir
            $msg = str_replace(["\r", "\n"], ' ', $msg);

            $json = '';
            if ($ctx !== []) {
                $encoded = json_encode(
                    self::sanitize($ctx, 0),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                $json = ' ' . ($encoded === false ? '"[bağlam kodlanamadı]"' : $encoded);
            }

            $line = sprintf('[%s] %s: %s%s', date('c'), $level, $msg, $json) . PHP_EOL;
            @file_put_contents($dir . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Log yazılamıyorsa sessizce geç: loglama asla isteği düşürmemeli
        }
    }

    private static function sanitize(mixed $value, int $depth): mixed
    {
        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'message' => $value->getMessage(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
            ];
        }
        if (is_array($value)) {
            if ($depth >= self::MAX_DEPTH) {
                return '[derinlik sınırı]';
            }
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = is_string($k) && preg_match(self::SENSITIVE_PATTERN, $k) === 1
                    ? '***'
                    : self::sanitize($v, $depth + 1);
            }
            return $out;
        }
        if (is_string($value)) {
            return mb_substr($value, 0, 2000, 'UTF-8');
        }
        if (is_object($value)) {
            return '[nesne ' . $value::class . ']';
        }
        if (is_resource($value)) {
            return '[kaynak]';
        }
        return $value;
    }
}