<?php
// routes/web.php

declare(strict_types=1);

use App\Controllers\Front\AccountController;
use App\Controllers\Front\AddressController;
use App\Controllers\Front\AuthController;
use App\Controllers\Front\CartController;
use App\Controllers\Front\CategoryController;
use App\Controllers\Front\CheckoutController;
use App\Controllers\Front\ContactController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\OrderHistoryController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\PaymentController;
use App\Controllers\Front\ProductController;
use App\Controllers\Front\SearchController;
use App\Controllers\Front\SitemapController;
use App\Core\Database;
use App\Core\Response;

/** @var \App\Core\Router $router */

// secure_headers + csrf config/middleware.php global listesinden gelir; burada tekrarlanmaz.
$guard = static fn (string ...$middleware): array => ['middleware' => $middleware];

// Yalnızca geliştirme ortamında sağlık ucu (üretimde rota hiç kayıtlı olmaz).
if (config('app.env') === 'local') {
    $router->get('/_health', static function (): Response {
        $dbUp = true;
        try {
            Database::instance()->fetchValue('SELECT 1');
        } catch (\Throwable) {
            $dbUp = false;
        }

        return Response::json(['ok' => true, 'data' => ['status' => 'up', 'db' => $dbUp, 'time' => now()]]);
    });
}

// ---------------------------------------------------------------------------
// GENEL (oturum şartı yok). Statik yollar parametreli olanlardan önce.
// ---------------------------------------------------------------------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/sayfa/{slug}', [PageController::class, 'show']);
$router->get('/iletisim', [ContactController::class, 'show']);
$router->post('/iletisim', [ContactController::class, 'submit'], $guard('throttle:5,60'));

// Katalog
$router->get('/urunler', [ProductController::class, 'index']);
$router->get('/urun/{slug}', [ProductController::class, 'show']);
$router->get('/kategori/{slug}', [CategoryController::class, 'show']);
$router->get('/yore/{slug}', [ProductController::class, 'byRegion']);
$router->get('/ara/oneri', [SearchController::class, 'suggest'], $guard('throttle:60,60'));
$router->get('/ara', [SearchController::class, 'index'], $guard('throttle:60,60'));
$router->get('/sitemap.xml', [SitemapController::class, 'index']);

// Sepet (misafir sepeti oturumla çalışır, auth yok)
$router->get('/sepet', [CartController::class, 'index']);
$router->get('/sepet/ozet', [CartController::class, 'summary']);
$router->post('/sepet/ekle', [CartController::class, 'add'], $guard('throttle:60,60'));
$router->post('/sepet/guncelle', [CartController::class, 'update']);
$router->post('/sepet/sil', [CartController::class, 'remove']);
$router->post('/sepet/kupon/kaldir', [CartController::class, 'removeCoupon']);
$router->post('/sepet/kupon', [CartController::class, 'applyCoupon'], $guard('throttle:10,60'));

// E-posta doğrulama bağlantısı: token tek başına yetkidir, oturum gerekmez.
$router->get('/eposta-dogrula/{token}', [AuthController::class, 'verifyEmail']);

// PayTR sunucu bildirimi: auth/guest grubunun DIŞINDA; CSRF istisnası config/security.php csrf_except'te, doğrulama hash ile.
$router->post('/odeme/paytr/bildirim', [PaymentController::class, 'paytrCallback']);

// ---------------------------------------------------------------------------
// MİSAFİR (giriş yapmış kullanıcı buraya giremez)
// ---------------------------------------------------------------------------
$router->group(['middleware' => ['guest']], static function () use ($router, $guard): void {
    $router->get('/giris', [AuthController::class, 'showLogin']);
    $router->post('/giris', [AuthController::class, 'login'], $guard('throttle:10,60'));
    $router->get('/kayit', [AuthController::class, 'showRegister']);
    $router->post('/kayit', [AuthController::class, 'register'], $guard('throttle:5,60'));
    $router->get('/sifremi-unuttum', [AuthController::class, 'showForgot']);
    $router->post('/sifremi-unuttum', [AuthController::class, 'forgot'], $guard('throttle:5,60'));
    $router->get('/sifre-sifirla/{token}', [AuthController::class, 'showReset']);
    $router->post('/sifre-sifirla', [AuthController::class, 'reset'], $guard('throttle:5,60'));
});

// ---------------------------------------------------------------------------
// AUTH (oturum zorunlu)
// ---------------------------------------------------------------------------
$router->group(['middleware' => ['auth']], static function () use ($router, $guard): void {
    // Oturum / doğrulama
    $router->post('/cikis', [AuthController::class, 'logout']);
    $router->get('/eposta-dogrula', [AuthController::class, 'showVerifyNotice']);
    $router->post('/eposta-dogrula/tekrar', [AuthController::class, 'resendVerification'], $guard('throttle:3,60'));

    // Checkout
    $router->get('/odeme', [CheckoutController::class, 'index']);
    $router->post('/odeme/ozet', [CheckoutController::class, 'summary']);
    $router->post('/odeme/siparis-ver', [CheckoutController::class, 'place'], $guard('throttle:10,60'));
    $router->get('/siparis/tamamlandi/{orderNumber}', [CheckoutController::class, 'complete']);

    // Ödeme sonuç sayfaları
    $router->get('/odeme/kart/{orderNumber}', [PaymentController::class, 'card']);
    $router->get('/odeme/basarili/{orderNumber}', [PaymentController::class, 'success']);
    $router->get('/odeme/basarisiz/{orderNumber}', [PaymentController::class, 'fail']);

    // Hesap
    $router->get('/hesabim', [AccountController::class, 'dashboard']);
    $router->get('/hesabim/siparisler', [OrderHistoryController::class, 'index']);
    $router->get('/hesabim/siparisler/{orderNumber}', [OrderHistoryController::class, 'show']);
    $router->post('/hesabim/siparisler/{orderNumber}/iptal', [OrderHistoryController::class, 'cancel']);
    $router->get('/hesabim/profil', [AccountController::class, 'profile']);
    $router->post('/hesabim/profil', [AccountController::class, 'updateProfile']);
    $router->get('/hesabim/sifre', [AccountController::class, 'passwordForm']);
    $router->post('/hesabim/sifre', [AccountController::class, 'changePassword']);

    // Adresler: statik /liste, {id} rotalarından ÖNCE.
    $router->get('/hesabim/adresler', [AccountController::class, 'addresses']);
    $router->get('/hesabim/adresler/liste', [AddressController::class, 'list']);
    $router->post('/hesabim/adresler', [AddressController::class, 'store']);
    $router->post('/hesabim/adresler/{id}/guncelle', [AddressController::class, 'update']);
    $router->post('/hesabim/adresler/{id}/sil', [AddressController::class, 'destroy']);
    $router->post('/hesabim/adresler/{id}/varsayilan', [AddressController::class, 'makeDefault']);
});