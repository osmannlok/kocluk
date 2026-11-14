# 19-route-sozlesme.md
Oturum 16: Route birleştirme. Kaynak: yazılan GERÇEK kod.

## (1) Dosya, sınıf, public metot imzaları
- routes/web.php: sınıf yok. Vitrin, üyelik, sepet, checkout, ödeme, hesap rotaları; $router (Router) ile yüklenir; yalnızca app.env=local iken GET /_health Closure'ı kaydeder.
- routes/admin.php: sınıf yok. GET /yonetim grup dışında (auth, perm:dashboard.view); diğer tüm yönetim rotaları prefix '/yonetim', taban [auth, perm:dashboard.view] grubunda, her biri kendi perm'iyle.
- scripts/route_check.php (yalnızca CLI): rota denetim betiği; Config::load ile .env gerekir.
  - final RouteRecorder: setFile(string $file): void | get(string $path, array|callable $handler, array $opts=[]): void | post(...) | put(...) | delete(...) (aynı imza) | group(array $attrs, callable $fn): void | routes(): array. Görev: route dosyalarını çalıştırıp rotaları (yöntem, tam yol, işleyici, middleware, dosya, sıra) kaydeder.
  - final RouteChecker: __construct(array $routes, array $globalMiddleware, array $aliases, array $csrfExcept) | run(): array (satırlar: check, level, method, path, detail). Görev: 5 denetimi çalıştırır.
  - function load_route_file(RouteRecorder $router, string $file): void | function pad_right(string $text, int $width): string
- Çıkış kodu: HATA seviyesinde satır varsa 1, yoksa 0. Seviyeler: HATA, UYARI, BILGI (yalnızca HATA çıkış kodunu etkiler).
- Denetimler: 1-PERM (auth/perm yok, yalnızca taban dashboard.view, bilinmeyen perm kodu) | 2-TEKRAR (aynı yol+yöntem; sıralama gölgelemesi) | 3-ISLEYICI (sınıf/metot/public, alias, throttle:N,M biçimi) | 4-URL-HARITASI (03 haritası betikte gömülü) | 5-CSRF.

## (2) Tablolar ve permission kodları
- Tablo kullanılmaz (/_health yalnızca SELECT 1).
- Permission kodları (rota başına): dashboard.view | product.view/create/update/delete | category.manage | stock.manage | order.view/update_status | report.view | payment.confirm | shipment.manage | customer.view | coupon.manage | banner.manage | menu.manage | page.manage | setting.manage | user.manage | role.manage | audit.view. order.cancel ve order.refund için rota YOK.
- Çift yetki (iki ayrı perm: girdisi): siparisler/disa-aktar (order.view + report.view); kategoriler/{id}/sil ve yoreler/{id}/sil (category.manage + product.delete).

## (3) JSON uç noktaları
- GET /_health (yalnızca local): yanıt {"ok":true,"data":{"status":"up","db":bool,"time":"Y-m-d H:i:s"}}.
- Başka uç tanımlanmadı; mevcut JSON uçları kendi sözleşmelerinde (07, 09, 11, 14 ve admin).

## (4) ROUTE SATIRLARI
Dosya: routes/web.php (global = secure_headers + csrf, config'ten)
GET /_health -> Closure | global (yalnızca app.env=local)
GET / -> HomeController@index | global
GET /sayfa/{slug} -> PageController@show | global
GET /iletisim -> ContactController@show | global
POST /iletisim -> ContactController@submit | throttle:5,60
GET /urunler -> ProductController@index | global
GET /urun/{slug} -> ProductController@show | global
GET /kategori/{slug} -> CategoryController@show | global
GET /yore/{slug} -> ProductController@byRegion | global
GET /ara/oneri -> SearchController@suggest | throttle:60,60
GET /ara -> SearchController@index | throttle:60,60
GET /sitemap.xml -> SitemapController@index | global
GET /sepet -> CartController@index | global
GET /sepet/ozet -> CartController@summary | global
POST /sepet/ekle -> CartController@add | throttle:60,60
POST /sepet/guncelle -> CartController@update | global
POST /sepet/sil -> CartController@remove | global
POST /sepet/kupon/kaldir -> CartController@removeCoupon | global
POST /sepet/kupon -> CartController@applyCoupon | throttle:10,60
GET /eposta-dogrula/{token} -> AuthController@verifyEmail | global
POST /odeme/paytr/bildirim -> PaymentController@paytrCallback | global (csrf_except; auth yok; throttle yok)
GET /giris -> AuthController@showLogin | guest
POST /giris -> AuthController@login | guest, throttle:10,60
GET /kayit -> AuthController@showRegister | guest
POST /kayit -> AuthController@register | guest, throttle:5,60
GET /sifremi-unuttum -> AuthController@showForgot | guest
POST /sifremi-unuttum -> AuthController@forgot | guest, throttle:5,60
GET /sifre-sifirla/{token} -> AuthController@showReset | guest
POST /sifre-sifirla -> AuthController@reset | guest, throttle:5,60
POST /cikis -> AuthController@logout | auth
GET /eposta-dogrula -> AuthController@showVerifyNotice | auth
POST /eposta-dogrula/tekrar -> AuthController@resendVerification | auth, throttle:3,60
GET /odeme -> CheckoutController@index | auth
POST /odeme/ozet -> CheckoutController@summary | auth
POST /odeme/siparis-ver -> CheckoutController@place | auth, throttle:10,60
GET /siparis/tamamlandi/{orderNumber} -> CheckoutController@complete | auth
GET /odeme/kart/{orderNumber} -> PaymentController@card | auth
GET /odeme/basarili/{orderNumber} -> PaymentController@success | auth
GET /odeme/basarisiz/{orderNumber} -> PaymentController@fail | auth
GET /hesabim -> AccountController@dashboard | auth
GET /hesabim/siparisler -> OrderHistoryController@index | auth
GET /hesabim/siparisler/{orderNumber} -> OrderHistoryController@show | auth
POST /hesabim/siparisler/{orderNumber}/iptal -> OrderHistoryController@cancel | auth
GET /hesabim/profil -> AccountController@profile | auth
POST /hesabim/profil -> AccountController@updateProfile | auth
GET /hesabim/sifre -> AccountController@passwordForm | auth
POST /hesabim/sifre -> AccountController@changePassword | auth
GET /hesabim/adresler -> AccountController@addresses | auth
GET /hesabim/adresler/liste -> AddressController@list | auth
POST /hesabim/adresler -> AddressController@store | auth
POST /hesabim/adresler/{id}/guncelle -> AddressController@update | auth
POST /hesabim/adresler/{id}/sil -> AddressController@destroy | auth
POST /hesabim/adresler/{id}/varsayilan -> AddressController@makeDefault | auth
Dosya: routes/admin.php (Controller: App\Controllers\Admin\*; tüm /yonetim rotaları auth + perm:dashboard.view tabanı taşır)
GET /yonetim -> DashboardController@index | auth, perm:dashboard.view
GET /yonetim/siparisler -> OrderAdminController@index | perm:order.view
GET /yonetim/siparisler/disa-aktar -> OrderAdminController@exportCsv | perm:order.view, perm:report.view
GET /yonetim/siparisler/{id}/yazdir -> OrderAdminController@print | perm:order.view
GET /yonetim/siparisler/{id} -> OrderAdminController@show | perm:order.view
GET /yonetim/siparisler/{orderNumber} -> OrderAdminController@showByNumber | perm:order.view
POST /yonetim/siparisler/{id}/durum -> OrderAdminController@updateStatus | perm:order.update_status
POST /yonetim/siparisler/{id}/kargo -> OrderAdminController@addShipment | perm:shipment.manage
POST /yonetim/siparisler/{id}/kargo/{shipmentId}/guncelle -> OrderAdminController@updateShipment | perm:shipment.manage
POST /yonetim/siparisler/{id}/teslim -> OrderAdminController@markDelivered | perm:shipment.manage
POST /yonetim/siparisler/{id}/havale-onay -> OrderAdminController@confirmBank | perm:payment.confirm
POST /yonetim/siparisler/{id}/kapida-odeme-alindi -> OrderAdminController@markCodPaid | perm:payment.confirm
GET /yonetim/musteriler -> CustomerAdminController@index | perm:customer.view
GET /yonetim/musteriler/{id} -> CustomerAdminController@show | perm:customer.view
GET /yonetim/urunler -> ProductAdminController@index | perm:product.view
GET /yonetim/urunler/olustur -> ProductAdminController@create | perm:product.create
POST /yonetim/urunler -> ProductAdminController@store | perm:product.create
GET /yonetim/urunler/{id}/duzenle -> ProductAdminController@edit | perm:product.update
POST /yonetim/urunler/{id} -> ProductAdminController@update | perm:product.update
POST /yonetim/urunler/{id}/sil -> ProductAdminController@destroy | perm:product.delete
POST /yonetim/urunler/{id}/aktif -> ProductAdminController@toggle | perm:product.update
POST /yonetim/urunler/{id}/gorsel -> ProductAdminController@uploadImage | perm:product.update
POST /yonetim/urunler/{id}/gorsel/sirala -> ProductAdminController@reorderImages | perm:product.update
POST /yonetim/urunler/{id}/gorsel/{imageId}/ana -> ProductAdminController@setPrimaryImage | perm:product.update
POST /yonetim/urunler/{id}/gorsel/{imageId}/sil -> ProductAdminController@deleteImage | perm:product.update
GET /yonetim/kategoriler -> CategoryAdminController@index | perm:category.manage
GET /yonetim/kategoriler/olustur -> CategoryAdminController@create | perm:category.manage
POST /yonetim/kategoriler -> CategoryAdminController@store | perm:category.manage
POST /yonetim/kategoriler/sirala -> CategoryAdminController@reorder | perm:category.manage
GET /yonetim/kategoriler/{id}/duzenle -> CategoryAdminController@edit | perm:category.manage
POST /yonetim/kategoriler/{id} -> CategoryAdminController@update | perm:category.manage
POST /yonetim/kategoriler/{id}/aktif -> CategoryAdminController@toggle | perm:category.manage
POST /yonetim/kategoriler/{id}/sil -> CategoryAdminController@destroy | perm:category.manage, perm:product.delete
GET /yonetim/yoreler -> RegionAdminController@index | perm:category.manage
GET /yonetim/yoreler/{id}/duzenle -> RegionAdminController@edit | perm:category.manage
POST /yonetim/yoreler -> RegionAdminController@store | perm:category.manage
POST /yonetim/yoreler/{id} -> RegionAdminController@update | perm:category.manage
POST /yonetim/yoreler/{id}/sil -> RegionAdminController@destroy | perm:category.manage, perm:product.delete
GET /yonetim/stok -> StockAdminController@index | perm:stock.manage
GET /yonetim/stok/kritik -> StockAdminController@lowStock | perm:stock.manage
POST /yonetim/stok/duzelt -> StockAdminController@adjust | perm:stock.manage
GET /yonetim/stok/{variantId}/hareketler -> StockAdminController@history | perm:stock.manage
GET /yonetim/kuponlar -> CouponAdminController@index | perm:coupon.manage
GET /yonetim/kuponlar/yeni -> CouponAdminController@create | perm:coupon.manage
POST /yonetim/kuponlar -> CouponAdminController@store | perm:coupon.manage
GET /yonetim/kuponlar/{id}/duzenle -> CouponAdminController@edit | perm:coupon.manage
POST /yonetim/kuponlar/{id}/guncelle -> CouponAdminController@update | perm:coupon.manage
POST /yonetim/kuponlar/{id}/durum -> CouponAdminController@toggle | perm:coupon.manage
POST /yonetim/kuponlar/{id}/sil -> CouponAdminController@destroy | perm:coupon.manage
GET /yonetim/bannerlar -> BannerAdminController@index | perm:banner.manage
GET /yonetim/bannerlar/yeni -> BannerAdminController@create | perm:banner.manage
POST /yonetim/bannerlar -> BannerAdminController@store | perm:banner.manage
POST /yonetim/bannerlar/sirala -> BannerAdminController@reorder | perm:banner.manage
GET /yonetim/bannerlar/{id}/duzenle -> BannerAdminController@edit | perm:banner.manage
POST /yonetim/bannerlar/{id}/guncelle -> BannerAdminController@update | perm:banner.manage
POST /yonetim/bannerlar/{id}/durum -> BannerAdminController@toggle | perm:banner.manage
POST /yonetim/bannerlar/{id}/sil -> BannerAdminController@destroy | perm:banner.manage
GET /yonetim/duyurular -> AnnouncementAdminController@index | perm:banner.manage
POST /yonetim/duyurular -> AnnouncementAdminController@store | perm:banner.manage
POST /yonetim/duyurular/{id}/guncelle -> AnnouncementAdminController@update | perm:banner.manage
POST /yonetim/duyurular/{id}/durum -> AnnouncementAdminController@toggle | perm:banner.manage
POST /yonetim/duyurular/{id}/sil -> AnnouncementAdminController@destroy | perm:banner.manage
GET /yonetim/menuler -> MenuAdminController@index | perm:menu.manage
GET /yonetim/menuler/{id} -> MenuAdminController@show | perm:menu.manage
POST /yonetim/menuler/{id}/sirala -> MenuAdminController@reorder | perm:menu.manage
POST /yonetim/menuler/{id}/ogeler -> MenuAdminController@storeItem | perm:menu.manage
POST /yonetim/menuler/{id}/ogeler/{itemId}/guncelle -> MenuAdminController@updateItem | perm:menu.manage
POST /yonetim/menuler/{id}/ogeler/{itemId}/durum -> MenuAdminController@toggleItem | perm:menu.manage
POST /yonetim/menuler/{id}/ogeler/{itemId}/sil -> MenuAdminController@destroyItem | perm:menu.manage
GET /yonetim/sayfalar -> PageAdminController@index | perm:page.manage
GET /yonetim/sayfalar/yeni -> PageAdminController@create | perm:page.manage
POST /yonetim/sayfalar -> PageAdminController@store | perm:page.manage
POST /yonetim/sayfalar/onizle -> PageAdminController@preview | perm:page.manage, throttle:60,60
GET /yonetim/sayfalar/{id}/duzenle -> PageAdminController@edit | perm:page.manage
POST /yonetim/sayfalar/{id}/guncelle -> PageAdminController@update | perm:page.manage
POST /yonetim/sayfalar/{id}/durum -> PageAdminController@toggle | perm:page.manage
POST /yonetim/sayfalar/{id}/sil -> PageAdminController@destroy | perm:page.manage
GET /yonetim/ayarlar -> SettingAdminController@index | perm:setting.manage
POST /yonetim/ayarlar -> SettingAdminController@update | perm:setting.manage, throttle:20,60
GET /yonetim/mesajlar -> ContactMessageAdminController@index | perm:customer.view
GET /yonetim/mesajlar/{id} -> ContactMessageAdminController@show | perm:customer.view
POST /yonetim/mesajlar/{id}/durum -> ContactMessageAdminController@setStatus | perm:customer.view
GET /yonetim/kullanicilar -> UserAdminController@index | perm:user.manage
GET /yonetim/kullanicilar/olustur -> UserAdminController@create | perm:user.manage
POST /yonetim/kullanicilar -> UserAdminController@store | perm:user.manage, throttle:10,60
GET /yonetim/kullanicilar/{id}/duzenle -> UserAdminController@edit | perm:user.manage
POST /yonetim/kullanicilar/{id}/guncelle -> UserAdminController@update | perm:user.manage
POST /yonetim/kullanicilar/{id}/askiya-al -> UserAdminController@suspend | perm:user.manage
POST /yonetim/kullanicilar/{id}/aktif-et -> UserAdminController@activate | perm:user.manage
POST /yonetim/kullanicilar/{id}/kilidi-ac -> UserAdminController@unlock | perm:user.manage
POST /yonetim/kullanicilar/{id}/oturumlari-kapat -> UserAdminController@forceLogout | perm:user.manage
POST /yonetim/kullanicilar/{id}/sifirlama-baglantisi -> UserAdminController@sendResetLink | perm:user.manage, throttle:10,60
GET /yonetim/roller -> RoleAdminController@index | perm:role.manage
GET /yonetim/roller/{id}/duzenle -> RoleAdminController@edit | perm:role.manage
POST /yonetim/roller/{id}/guncelle -> RoleAdminController@update | perm:role.manage
GET /yonetim/denetim -> AuditLogController@index | perm:audit.view
GET /yonetim/denetim/disa-aktar -> AuditLogController@export | perm:audit.view, throttle:5,60
GET /yonetim/denetim/{id} -> AuditLogController@show | perm:audit.view
GET /yonetim/guvenlik-olaylari -> AuditLogController@securityEvents | perm:audit.view
GET /yonetim/guvenlik-olaylari/disa-aktar -> AuditLogController@exportSecurity | perm:audit.view, throttle:5,60
Notlar: {itemId:\d+} yerine {itemId} (Router sözleşmesinde satır içi kısıt yok; controller int tipi 404 verir). {orderNumber} biçimi controller'da doğrulanır.

## (5) .env / settings anahtarları
- config: app.env (web.php /_health koşulu); route_check: middleware.global, middleware.aliases, security.csrf_except.
- .env: yeni anahtar yok (route_check .env'in varlığını ister: Config::load).
- settings tablosu kullanılmadı.