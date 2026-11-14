<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'name' => Env::get('APP_NAME', 'Egitim Oyun Portali'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', 'false') === 'true',
    'url' => Env::get('APP_URL', 'http://localhost'),
    'key' => Env::get('APP_KEY', ''),
    'timezone' => 'Europe/Istanbul',

    'session' => [
        'name' => Env::get('SESSION_NAME', 'oyun_portali_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', '120'),
        'secure' => Env::get('SESSION_SECURE', 'false') === 'true',
    ],

    'cache' => [
        'driver' => Env::get('CACHE_DRIVER', 'file'),
        'ttl' => (int) Env::get('CACHE_TTL', '3600'),
        'path' => BASE_PATH . '/storage/cache',
    ],

    'redis' => [
        'host' => Env::get('REDIS_HOST', '127.0.0.1'),
        'port' => (int) Env::get('REDIS_PORT', '6379'),
        'password' => Env::get('REDIS_PASSWORD', ''),
    ],

    'pagination' => [
        'per_page' => 12,
    ],
];