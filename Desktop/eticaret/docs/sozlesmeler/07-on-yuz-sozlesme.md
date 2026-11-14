# 07-on-yuz-sozlesme.md
Oturum 4: Ön yüz iskeleti. Kaynak: yazılan GERÇEK kod. Modeller instance çağrılır (new Banner())->...; View adı noktalı.

## (1) Dosya, sınıf, public metot imzaları
- app/Controllers/Front/HomeController.php: final HomeController extends BaseController | index(Request $request): Response. Görev: ana sayfa verisini toplar, view('front.home').
- app/Controllers/Front/PageController.php: final PageController | show(Request $request, string $slug): Response. Page::findActiveBySlug; yoksa HttpException(404); view('front.page').
- app/Controllers/Front/ContactController.php: final ContactController | show(Request $request): Response | submit(Request $request): Response. Honeypot "website" (doluysa kayıt yok, SecurityEvent 'contact_honeypot' info, sessiz başarı); ContactMessage::createMessage.
- resources/views/layouts/main.php: değişkenler $title, $metaDescription, $canonical, $content, $bodyClass, opsiyonel $ogImage, $cartCount, $settings. Setting::publicAll() -> View::share('settings'). Partial'lara ['settings'=>...] geçer. SVG sprite simge kimlikleri: #i-search,cart,user,menu,home,grid,x,chevron-down/right/left,check,truck,shield,lock,pin,phone,mail,instagram,facebook,whatsapp,package,leaf,arrow-right.
- Partial adları (View::partial): partials.announcement | partials.header | partials.flash | partials.footer | partials.mobile-nav | partials.cookie-banner (girdi: settings) | partials.pagination (girdi: $pager{page,pages,total}, $baseUrl yol; mevcut $_GET korunur) | partials.breadcrumb (girdi: $crumbs [[label,url|null]]) | partials.product-card (girdi: $product = LİSTE SATIRI 06).
- resources/views/errors/error.php: girdi $code,$title,$message,[$standalone]; layoutsuz çağrıda tam HTML, layoutla parça.
- resources/views/front/home.php: $heroBanners,$secondaryBanners,$categories,$featured,$newest,$regions,$freeThreshold | front/page.php: $page,$title,$metaDescription | front/contact.php: $settings.
- public/assets/js/app.js: window.App = {csrfToken(), fetchJson(url,{method,body,headers}), toast(msg,type), debounce(fn,wait), formatMoney(v), setCartCount(n), applyWidths(root)}.
  fetchJson HER ZAMAN çözülür: {ok:boolean,status:int,data:any|null,error:{code,message,errors}|null}. 401 -> toast + /giris; 419 -> yenile uyarısı; 422 -> error.errors döner; 429 -> toast; ağ hatası -> error.code "network".
  Olaylar/öznitelikler: document 'cart:changed' {detail:{count}}; [data-cart-count]; [data-confirm]; [data-no-lock]; [data-width]; [data-search-toggle]; [data-announcement-dismiss]; [data-add-to-cart][data-variant-id][data-quantity].
- public/assets/js/cookie-consent.js: App.onConsent(cb) (cb({level:'all'|'necessary',all:bool,ts})), App.getConsent(); [data-consent=all|necessary]; [data-consent-open]; #ymCookieBanner.
- public/assets/css/app.css, public/assets/img/logo.svg (currentColor; .ym-logo-mask ile boyanır).

## (2) Kullanılan tablolar ve permission kodları
- Tablolar (yalnızca model üzerinden): banners, categories, products(+variants/images, liste satırı), regions, pages, contact_messages, security_events, menus, menu_items, announcements, settings.
- Permission: dashboard.view (header'da "Yönetim Paneli" bağlantısı, can()).

## (3) JSON uç noktaları (istemci tarafı beklentisi)
- POST /sepet/ekle (app.js [data-add-to-cart]): istek JSON {"variant_id":int,"quantity":int}, başlık X-CSRF-Token; yanıt {"ok":true,"data":{"count":int}}; hata {"ok":false,"error":{code,message,errors}}. Sepet oturumu bu şekle UYMALI veya app.js güncellenmeli.
- Bu oturumda sunucu tarafı JSON ucu yazılmadı.

## (4) ROUTE SATIRLARI
GET / -> HomeController@index | global
GET /sayfa/{slug} -> PageController@show | global
GET /iletisim -> ContactController@show | global
POST /iletisim -> ContactController@submit | throttle:5,60
(routes/web.php'deki geçici GET / Closure'ı bununla değiştirilmeli; csrf global.)
Layout'un bağlantı verdiği, henüz yazılmamış URL'ler: /ara, /urunler, /urun/{slug}, /kategori/{slug}, /yore/{slug}, /sepet, /sepet/ekle, /odeme, /giris, /kayit, POST /cikis, /hesabim, /hesabim/siparisler, /yonetim, /sayfa/cerez, /sayfa/kvkk.

## (5) .env / settings anahtarları
- .env: kullanılmadı.
- settings (okunan): site.name, site.tagline, seo.default_title, seo.default_description, shipping.free_threshold, contact.email, contact.phone, contact.whatsapp, contact.address, social.instagram, social.facebook, legal.company_title, legal.tax_office, legal.tax_number, legal.mersis.

## Notlar (sonraki oturumlar)
- Vendor yolları (asset()): vendor/bootstrap/css/bootstrap.min.css, vendor/bootstrap/js/bootstrap.bundle.min.js (Bootstrap 5.3). Simgeler sprite'tır, ikon fontu gerekmez.
- Başlangıç sepet sayısı: View::share('cartCount', int) (yoksa 0). Mini-cart offcanvas (#ymCartOffcanvas) şimdilik /sepet bağlantısıdır.
- Menü offcanvas'ı #ymMenuOffcanvas; arama formu #ymSearch (GET /ara, alan q). Mobil menü için 'mobile', yoksa 'header' menüsü kullanılır.
- is_path() kalıpları: '/', '/ara', '/ara/*', '/sepet', '/sepet/*', '/hesabim', '/hesabim/*' (joker desteği varsayıldı).
- flash_messages() hem [{type,message}] hem {tip:[mesajlar]} biçimini kabul eder; errors() özet listeler.
- Ürün kartı alt metni boş (alt=""), ad bağlantı içinde; görsel yolu '/' ile başlar veya http(s).
- page.php yalnızca $page['content'] alanını ham basar (DB'de temizlenmiş HTML).
Sayfaya özel varlıklar: layouts.main isteğe bağlı $scripts (örn. ['auth.js']) ve $styles dizilerini kabul eder; controller view('...', ['scripts'=>[...]]) ile verir. Global yüklenenler: app.js, cookie-consent.js. Yeni sayfa JS'i layout'a elle eklenmez, $scripts ile verilir.