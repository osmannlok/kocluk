<?php // app/Core/Config.php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Yapılandırma deposu: .env ayrıştırıcı + config/*.php yükleyici.
 * .env değerleri $_ENV/getenv'e YAZILMAZ; yalnızca sınıf içi depoda tutulur.
 */
final class Config
{
    private static string $basePath = '';

    /** @var array<string, mixed> */
    private static array $env = [];

    /** @var array<string, array<string, mixed>> */
    private static array $items = [];

    public static function load(string $basePath): void
    {
        $real = realpath($basePath);
        self::$basePath = rtrim($real !== false ? $real : $basePath, '/');
        self::$env = self::parseEnvFile(self::$basePath . '/.env');
        self::$items = [];

        // config dosyaları env() çağırır; yardımcılar yüklü değilse yükle
        if (!function_exists('env')) {
            require_once self::$basePath . '/app/Helpers/functions.php';
        }

        $files = glob(self::$basePath . '/config/*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            // Kapsam sızıntısı olmasın diye izole closure içinde yüklenir
            $values = (static function (string $f): mixed {
                return require $f;
            })($file);
            if (is_array($values)) {
                self::$items[basename($file, '.php')] = $values;
            }
        }

        $tz = self::get('app.timezone');
        if (is_string($tz) && in_array($tz, timezone_identifiers_list(), true)) {
            date_default_timezone_set($tz);
        }
    }

    /** Noktalı anahtar erişimi: get('app.name'), get('database.options') */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** Proje kök yolu (yük öncesi çağrılırsa app/Core'a göre kök hesaplanır). */
    public static function basePath(string $sub = ''): string
    {
        $base = self::$basePath !== '' ? self::$basePath : dirname(__DIR__, 2);
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }

    /** .env deposundan değer okur (env() fonksiyonu bunu kullanır). */
    public static function env(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, self::$env) ? self::$env[$key] : $default;
    }

    /** @return array<string, mixed> */
    private static function parseEnvFile(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('.env dosyası bulunamadı veya okunamıyor.');
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        $result = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line; // BOM temizle
            }
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $name = trim(substr($line, 0, $pos));
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                continue;
            }
            $result[$name] = self::parseValue(substr($line, $pos + 1));
        }
        return $result;
    }

    private static function parseValue(string $raw): mixed
    {
        $raw = ltrim($raw);
        if ($raw === '') {
            return '';
        }

        // Çift tırnak: kaçış dizileri çözülür; tırnaklı değer her zaman string kalır
        if ($raw[0] === '"') {
            $out = '';
            $len = strlen($raw);
            for ($i = 1; $i < $len; $i++) {
                $ch = $raw[$i];
                if ($ch === '\\' && $i + 1 < $len) {
                    $n = $raw[++$i];
                    $out .= match ($n) {
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        '"' => '"',
                        '\\' => '\\',
                        '$' => '$',
                        default => '\\' . $n,
                    };
                    continue;
                }
                if ($ch === '"') {
                    return $out;
                }
                $out .= $ch;
            }
            return $out;
        }

        // Tek tırnak: olduğu gibi (kaçış yok)
        if ($raw[0] === "'") {
            $end = strpos($raw, "'", 1);
            return $end === false ? substr($raw, 1) : substr($raw, 1, $end - 1);
        }

        // Tırnaksız: satır sonu yorumunu at
        if ($raw[0] === '#') {
            return '';
        }
        $value = trim((string) preg_replace('/\s+#.*$/', '', $raw));

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }

        // Yalnızca güvenli tamsayılar dönüştürülür (baştaki sıfırlı/uzun değerler parola/anahtar olabilir).
        // Ondalıklılar bilerek string kalır: "1.50" gibi bir parola bozulmasın.
        if (preg_match('/^(0|-?[1-9]\d{0,14})$/', $value) === 1) {
            return (int) $value;
        }
        return $value;
    }
}