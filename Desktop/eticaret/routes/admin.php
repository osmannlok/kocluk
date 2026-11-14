<?php
// routes/admin.php

declare(strict_types=1);

use App\Controllers\Admin\AnnouncementAdminController;
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\BannerAdminController;
use App\Controllers\Admin\CategoryAdminController;
use App\Controllers\Admin\ContactMessageAdminController;
use App\Controllers\Admin\CouponAdminController;
use App\Controllers\Admin\CustomerAdminController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\MenuAdminController;
use App\Controllers\Admin\OrderAdminController;
use App\Controllers\Admin\PageAdminController;
use App\Controllers\Admin\ProductAdminController;
use App\Controllers\Admin\RegionAdminController;
use App\Controllers\Admin\RoleAdminController;
use App\Controllers\Admin\SettingAdminController;
use App\Controllers\Admin\StockAdminController;
use App\Controllers\Admin\UserAdminController;

/** @var \App\Core\Router $router */

// Her rota kendi permission'ını taşır (varsayılan: reddet); controller metotları ayrıca require() çağırır.
// Tüm yazma işlemleri POST'tur (silme dahil); CSRF global middleware'den gelir.
$guard = static fn (string ...$middleware): array => ['middleware' => $middleware];

// Kök yol, prefix birleştirme belirsizliği olmasın diye grubun dışında ve tam yolla tanımlanır.
$router->get('/yonetim', [DashboardController::class, 'index'], $guard('auth', 'perm:dashboard.view'));

$router->group(
    ['prefix' => '/yonetim', 'middleware' => ['auth', 'perm:dashboard.view']],
    static function () use ($router, $guard): void {
        // -------------------------------------------------------------- Siparişler
        // Statik yollar ({id} rotalarından) önce; {id}=\d+ olduğundan {orderNumber} ile çakışmaz.
        $router->get('/siparisler', [OrderAdminController::class, 'index'], $guard('perm:order.view'));
        $router->get('/siparisler/disa-aktar', [OrderAdminController::class, 'exportCsv'], $guard('perm:order.view', 'perm:report.view'));
        $router->get('/siparisler/{id}/yazdir', [OrderAdminController::class, 'print'], $guard('perm:order.view'));
        $router->get('/siparisler/{id}', [OrderAdminController::class, 'show'], $guard('perm:order.view'));
        $router->get('/siparisler/{orderNumber}', [OrderAdminController::class, 'showByNumber'], $guard('perm:order.view'));
        $router->post('/siparisler/{id}/durum', [OrderAdminController::class, 'updateStatus'], $guard('perm:order.update_status'));
        $router->post('/siparisler/{id}/kargo', [OrderAdminController::class, 'addShipment'], $guard('perm:shipment.manage'));
        $router->post('/siparisler/{id}/kargo/{shipmentId}/guncelle', [OrderAdminController::class, 'updateShipment'], $guard('perm:shipment.manage'));
        $router->post('/siparisler/{id}/teslim', [OrderAdminController::class, 'markDelivered'], $guard('perm:shipment.manage'));
        $router->post('/siparisler/{id}/havale-onay', [OrderAdminController::class, 'confirmBank'], $guard('perm:payment.confirm'));
        $router->post('/siparisler/{id}/kapida-odeme-alindi', [OrderAdminController::class, 'markCodPaid'], $guard('perm:payment.confirm'));

        // -------------------------------------------------------------- Müşteriler
        $router->get('/musteriler', [CustomerAdminController::class, 'index'], $guard('perm:customer.view'));
        $router->get('/musteriler/{id}', [CustomerAdminController::class, 'show'], $guard('perm:customer.view'));

        // -------------------------------------------------------------- Ürünler
        $router->get('/urunler', [ProductAdminController::class, 'index'], $guard('perm:product.view'));
        $router->get('/urunler/olustur', [ProductAdminController::class, 'create'], $guard('perm:product.create'));
        $router->post('/urunler', [ProductAdminController::class, 'store'], $guard('perm:product.create'));
        $router->get('/urunler/{id}/duzenle', [ProductAdminController::class, 'edit'], $guard('perm:product.update'));
        $router->post('/urunler/{id}', [ProductAdminController::class, 'update'], $guard('perm:product.update'));
        $router->post('/urunler/{id}/sil', [ProductAdminController::class, 'destroy'], $guard('perm:product.delete'));
        $router->post('/urunler/{id}/aktif', [ProductAdminController::class, 'toggle'], $guard('perm:product.update'));
        $router->post('/urunler/{id}/gorsel', [ProductAdminController::class, 'uploadImage'], $guard('perm:product.update'));
        $router->post('/urunler/{id}/gorsel/sirala', [ProductAdminController::class, 'reorderImages'], $guard('perm:product.update'));
        $router->post('/urunler/{id}/gorsel/{imageId}/ana', [ProductAdminController::class, 'setPrimaryImage'], $guard('perm:product.update'));
        $router->post('/urunler/{id}/gorsel/{imageId}/sil', [ProductAdminController::class, 'deleteImage'], $guard('perm:product.update'));

        // -------------------------------------------------------------- Kategoriler
        // Silme iki yetki ister: manager'da product.delete yok, silemez.
        $router->get('/kategoriler', [CategoryAdminController::class, 'index'], $guard('perm:category.manage'));
        $router->get('/kategoriler/olustur', [CategoryAdminController::class, 'create'], $guard('perm:category.manage'));
        $router->post('/kategoriler', [CategoryAdminController::class, 'store'], $guard('perm:category.manage'));
        $router->post('/kategoriler/sirala', [CategoryAdminController::class, 'reorder'], $guard('perm:category.manage'));
        $router->get('/kategoriler/{id}/duzenle', [CategoryAdminController::class, 'edit'], $guard('perm:category.manage'));
        $router->post('/kategoriler/{id}', [CategoryAdminController::class, 'update'], $guard('perm:category.manage'));
        $router->post('/kategoriler/{id}/aktif', [CategoryAdminController::class, 'toggle'], $guard('perm:category.manage'));
        $router->post('/kategoriler/{id}/sil', [CategoryAdminController::class, 'destroy'], $guard('perm:category.manage', 'perm:product.delete'));

        // -------------------------------------------------------------- Yöreler
        $router->get('/yoreler', [RegionAdminController::class, 'index'], $guard('perm:category.manage'));
        $router->get('/yoreler/{id}/duzenle', [RegionAdminController::class, 'edit'], $guard('perm:category.manage'));
        $router->post('/yoreler', [RegionAdminController::class, 'store'], $guard('perm:category.manage'));
        $router->post('/yoreler/{id}', [RegionAdminController::class, 'update'], $guard('perm:category.manage'));
        $router->post('/yoreler/{id}/sil', [RegionAdminController::class, 'destroy'], $guard('perm:category.manage', 'perm:product.delete'));

        // -------------------------------------------------------------- Stok
        $router->get('/stok', [StockAdminController::class, 'index'], $guard('perm:stock.manage'));
        $router->get('/stok/kritik', [StockAdminController::class, 'lowStock'], $guard('perm:stock.manage'));
        $router->post('/stok/duzelt', [StockAdminController::class, 'adjust'], $guard('perm:stock.manage'));
        $router->get('/stok/{variantId}/hareketler', [StockAdminController::class, 'history'], $guard('perm:stock.manage'));

        // -------------------------------------------------------------- Kuponlar
        $router->get('/kuponlar', [CouponAdminController::class, 'index'], $guard('perm:coupon.manage'));
        $router->get('/kuponlar/yeni', [CouponAdminController::class, 'create'], $guard('perm:coupon.manage'));
        $router->post('/kuponlar', [CouponAdminController::class, 'store'], $guard('perm:coupon.manage'));
        $router->get('/kuponlar/{id}/duzenle', [CouponAdminController::class, 'edit'], $guard('perm:coupon.manage'));
        $router->post('/kuponlar/{id}/guncelle', [CouponAdminController::class, 'update'], $guard('perm:coupon.manage'));
        $router->post('/kuponlar/{id}/durum', [CouponAdminController::class, 'toggle'], $guard('perm:coupon.manage'));
        $router->post('/kuponlar/{id}/sil', [CouponAdminController::class, 'destroy'], $guard('perm:coupon.manage'));

        // -------------------------------------------------------------- Bannerlar
        $router->get('/bannerlar', [BannerAdminController::class, 'index'], $guard('perm:banner.manage'));
        $router->get('/bannerlar/yeni', [BannerAdminController::class, 'create'], $guard('perm:banner.manage'));
        $router->post('/bannerlar', [BannerAdminController::class, 'store'], $guard('perm:banner.manage'));
        $router->post('/bannerlar/sirala', [BannerAdminController::class, 'reorder'], $guard('perm:banner.manage'));
        $router->get('/bannerlar/{id}/duzenle', [BannerAdminController::class, 'edit'], $guard('perm:banner.manage'));
        $router->post('/bannerlar/{id}/guncelle', [BannerAdminController::class, 'update'], $guard('perm:banner.manage'));
        $router->post('/bannerlar/{id}/durum', [BannerAdminController::class, 'toggle'], $guard('perm:banner.manage'));
        $router->post('/bannerlar/{id}/sil', [BannerAdminController::class, 'destroy'], $guard('perm:banner.manage'));

        // -------------------------------------------------------------- Duyurular (ayrı permission kodu yok: banner.manage)
        $router->get('/duyurular', [AnnouncementAdminController::class, 'index'], $guard('perm:banner.manage'));
        $router->post('/duyurular', [AnnouncementAdminController::class, 'store'], $guard('perm:banner.manage'));
        $router->post('/duyurular/{id}/guncelle', [AnnouncementAdminController::class, 'update'], $guard('perm:banner.manage'));
        $router->post('/duyurular/{id}/durum', [AnnouncementAdminController::class, 'toggle'], $guard('perm:banner.manage'));
        $router->post('/duyurular/{id}/sil', [AnnouncementAdminController::class, 'destroy'], $guard('perm:banner.manage'));

        // -------------------------------------------------------------- Menüler
        $router->get('/menuler', [MenuAdminController::class, 'index'], $guard('perm:menu.manage'));
        $router->get('/menuler/{id}', [MenuAdminController::class, 'show'], $guard('perm:menu.manage'));
        $router->post('/menuler/{id}/sirala', [MenuAdminController::class, 'reorder'], $guard('perm:menu.manage'));
        $router->post('/menuler/{id}/ogeler', [MenuAdminController::class, 'storeItem'], $guard('perm:menu.manage'));
        $router->post('/menuler/{id}/ogeler/{itemId}/guncelle', [MenuAdminController::class, 'updateItem'], $guard('perm:menu.manage'));
        $router->post('/menuler/{id}/ogeler/{itemId}/durum', [MenuAdminController::class, 'toggleItem'], $guard('perm:menu.manage'));
        $router->post('/menuler/{id}/ogeler/{itemId}/sil', [MenuAdminController::class, 'destroyItem'], $guard('perm:menu.manage'));

        // -------------------------------------------------------------- CMS sayfaları (/yeni ve /onizle {id}'den önce)
        $router->get('/sayfalar', [PageAdminController::class, 'index'], $guard('perm:page.manage'));
        $router->get('/sayfalar/yeni', [PageAdminController::class, 'create'], $guard('perm:page.manage'));
        $router->post('/sayfalar', [PageAdminController::class, 'store'], $guard('perm:page.manage'));
        $router->post('/sayfalar/onizle', [PageAdminController::class, 'preview'], $guard('perm:page.manage', 'throttle:60,60'));
        $router->get('/sayfalar/{id}/duzenle', [PageAdminController::class, 'edit'], $guard('perm:page.manage'));
        $router->post('/sayfalar/{id}/guncelle', [PageAdminController::class, 'update'], $guard('perm:page.manage'));
        $router->post('/sayfalar/{id}/durum', [PageAdminController::class, 'toggle'], $guard('perm:page.manage'));
        $router->post('/sayfalar/{id}/sil', [PageAdminController::class, 'destroy'], $guard('perm:page.manage'));

        // -------------------------------------------------------------- Ayarlar
        $router->get('/ayarlar', [SettingAdminController::class, 'index'], $guard('perm:setting.manage'));
        $router->post('/ayarlar', [SettingAdminController::class, 'update'], $guard('perm:setting.manage', 'throttle:20,60'));

        // -------------------------------------------------------------- İletişim mesajları (ayrı kod yok: customer.view)
        $router->get('/mesajlar', [ContactMessageAdminController::class, 'index'], $guard('perm:customer.view'));
        $router->get('/mesajlar/{id}', [ContactMessageAdminController::class, 'show'], $guard('perm:customer.view'));
        $router->post('/mesajlar/{id}/durum', [ContactMessageAdminController::class, 'setStatus'], $guard('perm:customer.view'));

        // -------------------------------------------------------------- Kullanıcılar ve roller (yalnızca admin)
        $router->get('/kullanicilar', [UserAdminController::class, 'index'], $guard('perm:user.manage'));
        $router->get('/kullanicilar/olustur', [UserAdminController::class, 'create'], $guard('perm:user.manage'));
        $router->post('/kullanicilar', [UserAdminController::class, 'store'], $guard('perm:user.manage', 'throttle:10,60'));
        $router->get('/kullanicilar/{id}/duzenle', [UserAdminController::class, 'edit'], $guard('perm:user.manage'));
        $router->post('/kullanicilar/{id}/guncelle', [UserAdminController::class, 'update'], $guard('perm:user.manage'));
        $router->post('/kullanicilar/{id}/askiya-al', [UserAdminController::class, 'suspend'], $guard('perm:user.manage'));
        $router->post('/kullanicilar/{id}/aktif-et', [UserAdminController::class, 'activate'], $guard('perm:user.manage'));
        $router->post('/kullanicilar/{id}/kilidi-ac', [UserAdminController::class, 'unlock'], $guard('perm:user.manage'));
        $router->post('/kullanicilar/{id}/oturumlari-kapat', [UserAdminController::class, 'forceLogout'], $guard('perm:user.manage'));
        $router->post('/kullanicilar/{id}/sifirlama-baglantisi', [UserAdminController::class, 'sendResetLink'], $guard('perm:user.manage', 'throttle:10,60'));
        $router->get('/roller', [RoleAdminController::class, 'index'], $guard('perm:role.manage'));
        $router->get('/roller/{id}/duzenle', [RoleAdminController::class, 'edit'], $guard('perm:role.manage'));
        $router->post('/roller/{id}/guncelle', [RoleAdminController::class, 'update'], $guard('perm:role.manage'));

        // -------------------------------------------------------------- Denetim ve güvenlik olayları (disa-aktar {id}'den önce)
        $router->get('/denetim', [AuditLogController::class, 'index'], $guard('perm:audit.view'));
        $router->get('/denetim/disa-aktar', [AuditLogController::class, 'export'], $guard('perm:audit.view', 'throttle:5,60'));
        $router->get('/denetim/{id}', [AuditLogController::class, 'show'], $guard('perm:audit.view'));
        $router->get('/guvenlik-olaylari', [AuditLogController::class, 'securityEvents'], $guard('perm:audit.view'));
        $router->get('/guvenlik-olaylari/disa-aktar', [AuditLogController::class, 'exportSecurity'], $guard('perm:audit.view', 'throttle:5,60'));
    }
);