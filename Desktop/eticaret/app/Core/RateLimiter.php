<?php
// app/Core/RateLimiter.php
declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    // Anahtar SHA-256 özetlenir: IP/e-posta gibi veriler tabloda açık yazılmaz.
    private static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    public static function tooManyAttempts(string $key, int $max, int $seconds): bool
    {
        $row = Database::instance()->fetchOne(
            'SELECT hits, expires_at FROM rate_limits WHERE key_hash = :h',
            ['h' => self::hash($key)]
        );
        if ($row === null || $row === false || (int) $row['expires_at'] <= time()) {
            return false;
        }
        return (int) $row['hits'] >= $max;
    }

    // Sabit pencere; tek atomik sorgu: yarış durumunda sayaç kaybolmaz.
    public static function hit(string $key, int $seconds): void
    {
        $now = time();
        $exp = $now + max(1, $seconds);
        Database::instance()->execute(
            'INSERT INTO rate_limits (key_hash, hits, window_start, expires_at)
             VALUES (:h, 1, :ws, :exp)
             ON DUPLICATE KEY UPDATE
                hits = IF(expires_at <= :n1, 1, hits + 1),
                window_start = IF(expires_at <= :n2, :n3, window_start),
                expires_at = IF(expires_at <= :n4, :exp2, expires_at)',
            [
                'h' => self::hash($key),
                'ws' => $now,
                'exp' => $exp,
                'n1' => $now,
                'n2' => $now,
                'n3' => $now,
                'n4' => $now,
                'exp2' => $exp,
            ]
        );
        self::purge();
    }

    public static function clear(string $key): void
    {
        Database::instance()->execute('DELETE FROM rate_limits WHERE key_hash = :h', ['h' => self::hash($key)]);
    }

    public static function availableIn(string $key): int
    {
        $row = Database::instance()->fetchOne(
            'SELECT expires_at FROM rate_limits WHERE key_hash = :h',
            ['h' => self::hash($key)]
        );
        if ($row === null || $row === false) {
            return 0;
        }
        return max(0, (int) $row['expires_at'] - time());
    }

    // Süresi dolanlar fırsat buldukça (yaklaşık %2 istekte) temizlenir.
    private static function purge(): void
    {
        if (random_int(1, 50) === 1) {
            Database::instance()->execute('DELETE FROM rate_limits WHERE expires_at <= :now', ['now' => time()]);
        }
    }
}