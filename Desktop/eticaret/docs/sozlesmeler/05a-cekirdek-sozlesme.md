# 05a-cekirdek-sozlesme.md
# Oturum 2a: Çekirdek (config, yardımcılar, temel sınıflar) — YAZILAN GERÇEK KODA GÖRE

## 1) DOSYALAR / SINIFLAR / METOTLAR
- .env.example, public/.htaccess, public/uploads/.htaccess, docs/apache-vhost.conf: yapılandırma/sertleştirme (sınıf yok).
- config/app.php: name, env, debug(bool), url(sonda / yok), key, timezone, locale, trusted_proxies(dizi), session[cookie_name,idle_minutes,absolute_minutes], upload[max_mb,allowed_mimes], mail[driver,host,port,user,pass,encryption,from_address].
- config/database.php: host,port,database,username,password,charset,collation,options(PDO sabitleri; init: time_zone +03:00).
- config/middleware.php: aliases(auth,guest,perm,throttle,csrf,secure_headers => App\Middleware\{Auth,Guest,Permission,Throttle,Csrf,SecurityHeaders}), global[secure_headers,csrf].
- config/security.php: csp(direktif=>dizi), csrf_except(['/odeme/paytr/bildirim']), hsts(bool).
- app/Core/Config.php (final): static load(string $basePath): void | get(string $key, mixed $default=null): mixed | basePath(string $sub=''): string | env(string $key, mixed $default=null): mixed [EK: sözleşmede yoktu, env() için gerekli]. .env yoksa RuntimeException; load() app.timezone'u uygular.
- app/Core/Database.php (final): static instance(): self [EK: sözleşmede yoktu] | pdo(): PDO | fetchAll(string,array=[]): array | fetchOne(...): ?array | fetchValue(...): mixed | execute(...): int | insert(...): int | transaction(callable): mixed. Parametreler tipe göre bağlanır; PDOException loglanıp aynen fırlatılır.
- app/Core/Logger.php (final): static error|warning|info(string $msg, array $ctx=[]): void. Hassas anahtarlar "***".
- app/Core/HttpException.php: __construct(int $status, string $message=''); getStatus(): int.
- app/Core/ValidationException.php: __construct(array $errors, array $old=[]); errors(): array; old(): array (parola/token/kart/tc alanları old'dan atılır).
- app/Core/Request.php: __construct(array $server,array $queryParams,array $postParams,array $files,?string $rawBody=null) | static capture(): self | method(): string | path(): string | input(string,mixed=null): mixed | all(): array | query(?string=null,mixed=null): mixed | post(?string=null,mixed=null): mixed | json(): array | file(string): ?array | ip(): string | userAgent(): string | isAjax(): bool | wantsJson(): bool | header(string): ?string | routeParam(string,mixed=null): mixed | setRouteParams(array): void.
- app/Core/Response.php: static html(string,int=200)|json(array,int=200)|text(string,int=200)|redirect(string,int=302): self | withHeader(string,string): self (aynı nesne) | send(): void. JSON Cache-Control: no-store.
- app/Helpers/Money.php (final, statik): toCents(string|int|float): int (int = TL) | fromCents(int): string | percent(int,string): int | format(string): string. Hata: InvalidArgumentException.
- app/Helpers/functions.php (global): env, config, e(mixed):string, url(string=''):string, asset(string):string, route(string,array=[]):string, redirect(string,int=302):Response, back(string='/'):Response, old(string,mixed=null):mixed, errors(?string=null):array|string|null, flash(string,string):void, flash_messages():array, csrf_field():string, csrf_token():string, method_field(string):string, auth():?array, can(string):bool, money(string|int|float):string, slugify(string):string, now():string, ip_to_bin(string):?string, bin_to_ip(?string):string, abort(int,string=''):never, is_path(string):bool.

## 2) TABLOLAR / PERMISSION
- Tablo kullanılmaz (Database yalnızca altyapı). Permission kodu kullanılmaz (can() → Auth::can).

## 3) JSON UÇ NOKTALARI
- Yok. Yanıt formatı Response::json'a verilen diziyle belirlenir (BaseController::ok/fail üretecek).

## 4) ROUTE SATIRLARI
- Yok (bu oturumda route/controller yazılmadı).

## 5) .env / SETTINGS ANAHTARLARI
- .env: APP_NAME, APP_ENV, APP_DEBUG, APP_URL, APP_KEY, TRUSTED_PROXIES, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, SESSION_COOKIE_NAME, SESSION_IDLE_MINUTES, SESSION_ABSOLUTE_MINUTES, MAIL_DRIVER, MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_ENCRYPTION, MAIL_FROM_ADDRESS, UPLOAD_MAX_MB, PAYTR_MERCHANT_ID, PAYTR_MERCHANT_KEY, PAYTR_MERCHANT_SALT, PAYTR_TEST_MODE, PAYTR_DEBUG_ON, PAYTR_TIMEOUT_LIMIT, PAYTR_MAX_INSTALLMENT.
- settings tablosu anahtarı kullanılmadı.

## DİĞER OTURUMLARA NOTLAR (bağımlılıklar)
- Session::pullFlash anahtarları: 'old' (dizi) ve 'errors' (alan=>mesaj|dizi); ErrorHandler bunları Session::flash ile yazmalı.
- flash() bildirimleri Session::get/put/forget ile '_flash_messages' anahtarında tutar.
- Csrf::field() HTML <input name="_token"> döndürmeli; Auth::user()/can() sözleşmedeki imzalarla çağrılır.
- Request::capture() her çağrıda yeni nesne üretir; Router/ErrorHandler kendi Request'ini kullanmalı.
- config/*.php dosyaları env() global fonksiyonuna dayanır; composer.json "autoload.files" içine app/Helpers/functions.php eklenmeli (Config::load yedek olarak require_once yapar).
- Ondalıklı .env değerleri string kalır; tamsayılar int olur (DB_PASS gibi alanlar config'te (string) ile zorlanır).