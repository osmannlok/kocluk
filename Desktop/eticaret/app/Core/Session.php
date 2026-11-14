<?php
// app/Core/Session.php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $cfg = (array) Config::get('app.session', []);
        $cookieName = (string) ($cfg['cookie_name'] ?? 'ym_session');
        if (!preg_match('/^[A-Za-z0-9_]{1,40}$/', $cookieName)) {
            $cookieName = 'ym_session';
        }
        $idle = max(60, (int) ($cfg['idle_minutes'] ?? 30) * 60);
        $absolute = max($idle, (int) ($cfg['absolute_minutes'] ?? 720) * 60);
        $secure = self::isHttps();

        // Sıkı ayarlar: kimlik yalnızca çerezden, JS erişemez, çapraz site POST'ta çerez gitmez.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.gc_maxlifetime', (string) $absolute);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        session_name($cookieName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_set_save_handler(new DbSessionHandler($absolute), true);
        session_start();

        self::guard($idle, $absolute);
        self::rotateFlash();
    }

    public static function get(string $k, mixed $d = null): mixed
    {
        self::ensureActive();
        return $_SESSION[$k] ?? $d;
    }

    public static function put(string $k, mixed $v): void
    {
        self::ensureActive();
        $_SESSION[$k] = $v;
    }

    public static function has(string $k): bool
    {
        self::ensureActive();
        return isset($_SESSION[$k]);
    }

    public static function forget(string $k): void
    {
        self::ensureActive();
        unset($_SESSION[$k]);
    }

    // Flash: bir sonraki istekte okunabilir, ondan sonraki istekte düşer.
    public static function flash(string $k, mixed $v): void
    {
        self::ensureActive();
        $_SESSION['_flash_new'][$k] = $v;
    }

    // Okuma silmez; veri zaten sonraki istek başında düşer (old()/errors() birden çok kez okunabilsin).
    public static function pullFlash(string $k, mixed $d = null): mixed
    {
        self::ensureActive();
        if (isset($_SESSION['_flash_old']) && array_key_exists($k, $_SESSION['_flash_old'])) {
            return $_SESSION['_flash_old'][$k];
        }
        if (isset($_SESSION['_flash_new']) && array_key_exists($k, $_SESSION['_flash_new'])) {
            return $_SESSION['_flash_new'][$k];
        }
        return $d;
    }

    public static function regenerate(): void
    {
        self::ensureActive();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (!headers_sent()) {
            $p = session_get_cookie_params();
            setcookie((string) session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => (bool) $p['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function id(): string
    {
        $id = session_id();
        return $id === false ? '' : $id;
    }

    private static function ensureActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }
    }

    // Boşta + mutlak zaman aşımı ve user-agent kontrolü: çalınan çerezin başka tarayıcıda kullanımını zorlaştırır.
    private static function guard(int $idle, int $absolute): void
    {
        $now = time();
        $ua = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if (!isset($_SESSION['_created'])) {
            self::initMeta($now, $ua);
            return;
        }

        $created = (int) $_SESSION['_created'];
        $last = (int) ($_SESSION['_last'] ?? $created);
        $savedUa = (string) ($_SESSION['_ua'] ?? '');

        $expired = ($now - $created > $absolute) || ($now - $last > $idle);
        $uaChanged = !hash_equals($savedUa, $ua);

        if ($expired || $uaChanged) {
            if ($uaChanged) {
                Logger::warning('Oturum user-agent değişimi nedeniyle düşürüldü');
            }
            $_SESSION = [];
            session_regenerate_id(true);
            self::initMeta($now, $ua);
            return;
        }

        $_SESSION['_last'] = $now;
    }

    private static function initMeta(int $now, string $ua): void
    {
        $_SESSION['_created'] = $now;
        $_SESSION['_last'] = $now;
        $_SESSION['_ua'] = $ua;
    }

    private static function rotateFlash(): void
    {
        $new = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_old'] = is_array($new) ? $new : [];
        $_SESSION['_flash_new'] = [];
    }

    private static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        $proxies = (array) Config::get('app.trusted_proxies', []);
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remote !== '' && in_array($remote, $proxies, true)
            && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }
        return str_starts_with((string) Config::get('app.url', ''), 'https://');
    }
}