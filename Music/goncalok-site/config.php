<?php
// ============================================================
// GONCA LÖK — Uygulama önyüklemesi (.env, DB, güvenlik başlıkları,
// loglama, hız sınırlama, oturum yardımcıları)
// ============================================================
declare(strict_types=1);

// ---- .env yükleyici (basit key=value; değerler " tırnak içinde olabilir) ----
function app_load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if ($key === '') {
            continue;
        }
        $len = strlen($value);
        if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false && !isset($_SERVER[$key])) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

app_load_env(__DIR__ . '/.env');

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

define('APP_ENV', (string) env('APP_ENV', 'production'));
define('APP_DEBUG', APP_ENV === 'development');

date_default_timezone_set((string) env('APP_TIMEZONE', 'Europe/Istanbul'));

// ---- Veritabanı ayarları ----
define('DB_HOST', (string) env('DB_HOST', 'localhost'));
define('DB_NAME', (string) env('DB_NAME', 'goncalok'));
define('DB_USER', (string) env('DB_USER', 'goncalok'));
define('DB_PASS', (string) env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');
define('DB_TIMEZONE', (string) env('DB_TIMEZONE', '+03:00'));

// Bu siteye servis edilen organizasyonun slug'ı (public sayfalar / randevu)
define('SITE_ORG_SLUG', (string) env('SITE_ORG_SLUG', 'gonca-lok'));

// ---- Uygulama / bildirim ayarları ----
// APP_URL boş bırakılırsa istekten otomatik tespit edilir.
define('APP_URL', (string) env('APP_URL', ''));
define('MAIL_ENABLED', filter_var(env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN));
define('MAIL_FROM', (string) env('MAIL_FROM', 'goncalokofficial@gmail.com'));
define('MAIL_FROM_NAME', (string) env('MAIL_FROM_NAME', 'Gonca LÖK'));
define('PORTAL_TOKEN_DAYS', max(1, (int) env('PORTAL_TOKEN_DAYS', 30)));

// Oturum zaman aşımları (saniye)
define('SESSION_IDLE_SECONDS_ADMIN', max(300, (int) env('SESSION_IDLE_SECONDS_ADMIN', 1800)));
define('SESSION_IDLE_SECONDS_PORTAL', max(3600, (int) env('SESSION_IDLE_SECONDS_PORTAL', 7 * 24 * 3600)));

// ------------------------------------------------------------
// Loglama: storage/logs/app.log  (tarih | seviye | mesaj | dosya:satır | bağlam)
// Şifre, token, cookie ve benzeri anahtarlar otomatik maskelenir.
// ------------------------------------------------------------
function app_log_file(): string
{
    $dir = __DIR__ . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . '/app.log';
}

function app_log_redact(array $context): array
{
    $sensitive = ['password', 'pass', 'token', 'secret', 'csrf', 'authorization', 'cookie', 'private_note'];
    $out = [];
    foreach ($context as $key => $value) {
        $lower = strtolower((string) $key);
        foreach ($sensitive as $needle) {
            if (str_contains($lower, $needle)) {
                $out[$key] = '[REDACTED]';
                continue 2;
            }
        }
        $out[$key] = is_scalar($value) || $value === null ? $value : '[' . gettype($value) . ']';
    }
    return $out;
}

function app_log(string $level, string $message, array $context = []): void
{
    $level = strtoupper($level);
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $frame = $trace[0] ?? [];
    $file = isset($frame['file']) ? basename((string) $frame['file']) : '?';
    $line = isset($frame['line']) ? (int) $frame['line'] : 0;
    $contextJson = app_log_redact($context);
    $lineText = sprintf(
        "[%s] %s | %s | %s:%d%s\n",
        date('Y-m-d H:i:s'),
        $level,
        $message,
        $file,
        $line,
        $contextJson ? ' | ' . json_encode($contextJson, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : ''
    );
    @file_put_contents(app_log_file(), $lineText, FILE_APPEND | LOCK_EX);
}

// Beklenmeyen hataları logla; production'da genel 500 sayfası göster
function app_handle_exception(Throwable $e): void
{
    app_log('ERROR', $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Bir sorun oluştu | Gonca LÖK</title>
</head>'
            . '<body><h1>Bir sorun oluştu</h1><p>İşleminiz tamamlanamadı. Lütfen daha sonra tekrar deneyin.</p></body></html>';
    }
    exit;
}
set_exception_handler('app_handle_exception');
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        app_log('FATAL', (string) $error['message'], ['file' => $error['file'] ?? '', 'line' => $error['line'] ?? 0]);
        if (!headers_sent()) {
            http_response_code(500);
        }
    }
});

// ------------------------------------------------------------
// Güvenlik başlıkları (her isteğe uygulanır)
// ------------------------------------------------------------
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "font-src 'self'; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
    );
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
send_security_headers();

// ------------------------------------------------------------
// Oturum yardımcıları
// ------------------------------------------------------------
function app_session_start(string $purpose = 'web'): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name($purpose === 'portal' ? 'GONCALOKPORTAL' : 'GONCALOKADMIN');
    session_start();
}

// Boşta kalma süresini denetler; süre dolduysa oturumu düşürür. (true = süresi doldu)
function session_idle_expired(int $maxIdleSeconds): bool
{
    $now = time();
    $last = isset($_SESSION['_last_activity']) ? (int) $_SESSION['_last_activity'] : $now;
    if ($now - $last > $maxIdleSeconds) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        return true;
    }
    $_SESSION['_last_activity'] = $now;
    return false;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

// Oturumdaki kullanıcı (yoksa null)
function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) && isset($user['id']) ? $user : null;
}

function current_user_id(): int
{
    $user = current_user();
    return $user ? (int) $user['id'] : 0;
}

// Oturumdaki kullanıcının organizasyonu; public bağlamlarda SITE_ORG_SLUG'a düşer.
function current_org_id(): int
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    // Portal oturumu (öğrenci/veli): token'dan gelen organizasyon
    if (!empty($_SESSION['portal_organization_id'])) {
        $cached = (int) $_SESSION['portal_organization_id'];
        return $cached;
    }
    $user = current_user();
    if ($user && (int) ($user['organization_id'] ?? 0) > 0) {
        $cached = (int) $user['organization_id'];
        return $cached;
    }
    try {
        $stmt = db()->prepare('SELECT id FROM organizations WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => SITE_ORG_SLUG]);
        $id = (int) $stmt->fetchColumn();
        $cached = $id > 0 ? $id : 1;
    } catch (Throwable $e) {
        $cached = 1;
    }
    return $cached;
}

// ------------------------------------------------------------
// PDO
// ------------------------------------------------------------
function db(): PDO
{
    static $instance = null;
    if ($instance === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $instance = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $instance->exec("SET time_zone = '" . str_replace("'", '', DB_TIMEZONE) . "'");
    }
    return $instance;
}

// ------------------------------------------------------------
// Basit hız sınırlama (rate_limits tablosu; yoksa sessizce izin verir)
// ------------------------------------------------------------
function rate_limit_hit(string $bucket, string $identifier, int $limit, int $windowSeconds): bool
{
    try {
        $pdo = db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rate_limits (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                bucket VARCHAR(64) NOT NULL,
                identifier VARCHAR(190) NOT NULL,
                hits INT NOT NULL DEFAULT 1,
                window_start DATETIME NOT NULL,
                KEY idx_rate (bucket, identifier, window_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);
        $pdo->prepare('DELETE FROM rate_limits WHERE window_start < :old')->execute([':old' => date('Y-m-d H:i:s', time() - 86400)]);

        $stmt = $pdo->prepare('SELECT id, hits FROM rate_limits WHERE bucket = :bucket AND identifier = :identifier AND window_start >= :window ORDER BY id DESC LIMIT 1');
        $stmt->execute([':bucket' => $bucket, ':identifier' => $identifier, ':window' => $windowStart]);
        $row = $stmt->fetch();
        if ($row) {
            if ((int) $row['hits'] >= $limit) {
                return false;
            }
            $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE id = :id')->execute([':id' => (int) $row['id']]);
            return true;
        }
        $pdo->prepare('INSERT INTO rate_limits (bucket, identifier, hits, window_start) VALUES (:bucket, :identifier, 1, NOW())')
            ->execute([':bucket' => $bucket, ':identifier' => $identifier]);
        return true;
    } catch (Throwable $e) {
        app_log('WARNING', 'rate_limit_hit failed; allowing request', ['bucket' => $bucket, 'error' => $e->getMessage()]);
        return true;
    }
}

// ------------------------------------------------------------
// Kimlik doğrulama yardımcıları (admin paneli)
// ------------------------------------------------------------
function auth_find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare(
        'SELECT u.*, o.slug AS organization_slug, o.name AS organization_name
         FROM users u JOIN organizations o ON o.id = u.organization_id
         WHERE u.email = :email LIMIT 1'
    );
    $stmt->execute([':email' => mb_strtolower(trim($email))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function auth_create_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'organization_id' => (int) $user['organization_id'],
        'organization_name' => (string) ($user['organization_name'] ?? ''),
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
    $_SESSION['_last_activity'] = time();
}

function auth_attempt_login(string $email, string $password): array
{
    $ip = client_ip();
    if (!rate_limit_hit('admin_login', $ip, 5, 900)) {
        app_log('WARNING', 'admin_login rate limited', ['ip' => $ip]);
        return ['ok' => false, 'error' => 'Çok fazla deneme yapıldı. Lütfen 15 dakika sonra tekrar deneyin.'];
    }
    $user = auth_find_user_by_email($email);
    if (!$user || (int) $user['active'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
        app_log('INFO', 'admin_login failed', ['ip' => $ip, 'email' => $email]);
        return ['ok' => false, 'error' => 'E-posta veya şifre hatalı.'];
    }
    auth_create_session($user);
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([':id' => (int) $user['id']]);
    app_log('INFO', 'admin_login succeeded', ['user_id' => (int) $user['id'], 'ip' => $ip]);
    return ['ok' => true];
}

// Satır içi SVG ikon seti — tüm sayfalarda erişilebilir
require_once __DIR__ . '/partials/icons.php';
