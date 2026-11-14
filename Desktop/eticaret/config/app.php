<?php // config/app.php
declare(strict_types=1);

// Uygulama ayarları. Tüm değerler .env'den okunur (env() Config deposunu kullanır).
return [
    // Site adı
    'name' => (string) env('APP_NAME', 'Yöresel Market'),

    // Ortam: local | production
    'env' => (string) env('APP_ENV', 'production'),

    // Hata ayrıntısı: varsayılan KAPALI (ayrıntı sızıntısını önler)
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    // Sitenin kök adresi; sonundaki / atılır (yönlendirme ve url() bu değere güvenir)
    'url' => rtrim((string) env('APP_URL', 'http://eticaret.local'), '/'),

    // Uygulama anahtarı (çerez imzası vb.)
    'key' => (string) env('APP_KEY', ''),

    'timezone' => 'Europe/Istanbul',
    'locale' => 'tr',

    // Güvenilir proxy IP'leri: yalnızca bunlardan gelen X-Forwarded-For dikkate alınır
    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
        static fn (string $ip): bool => $ip !== ''
    )),

    'session' => [
        'cookie_name' => (string) env('SESSION_COOKIE_NAME', 'ym_session'),
        // Boşta kalma zaman aşımı (dakika)
        'idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 60),
        // Mutlak oturum ömrü (dakika)
        'absolute_minutes' => (int) env('SESSION_ABSOLUTE_MINUTES', 720),
    ],

    'upload' => [
        'max_mb' => (int) env('UPLOAD_MAX_MB', 5),
        // Beyaz liste: yalnızca bu MIME türleri kabul edilir
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],

    'mail' => [
        'driver' => (string) env('MAIL_DRIVER', 'log'),
        'host' => (string) env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', 587),
        'user' => (string) env('MAIL_USER', ''),
        'pass' => (string) env('MAIL_PASS', ''),
        'encryption' => (string) env('MAIL_ENCRYPTION', 'tls'),
        'from_address' => (string) env('MAIL_FROM_ADDRESS', ''),
    ],
];