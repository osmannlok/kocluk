<?php // config/middleware.php
declare(strict_types=1);

use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Middleware\Guest;
use App\Middleware\Permission;
use App\Middleware\SecurityHeaders;
use App\Middleware\Throttle;

return [
    // Rota tanımlarında kullanılan kısa adlar (perm:product.create, throttle:5,60 ...)
    'aliases' => [
        'auth' => Auth::class,
        'guest' => Guest::class,
        'perm' => Permission::class,
        'throttle' => Throttle::class,
        'csrf' => Csrf::class,
        'secure_headers' => SecurityHeaders::class,
    ],

    // Her istekte otomatik çalışır; sıra önemlidir (başlıklar önce, CSRF sonra)
    'global' => ['secure_headers', 'csrf'],
];