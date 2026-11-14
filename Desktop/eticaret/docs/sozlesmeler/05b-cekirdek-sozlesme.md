05b-cekirdek-sozlesme.md

Oturum 2b: Oturum, güvenlik, router, middleware, giriş noktası. Kaynak: yazılan GERÇEK kod.

(1) Dosya, sınıf, public metot imzaları
app/Core/DbSessionHandler.php: final DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface | __construct(int $lifetimeSeconds), open, close, read(string $id): string|false, write(string $id, string $data): bool, destroy(string $id): bool, gc(int $max_lifetime): int|false, validateId(string $id): bool, updateTimestamp(string $id, string $data): bool. sessions.id = SHA-256(oturum kimliği) saklar.
app/Core/Session.php: final Session | static start(): void, get(string $k, mixed $d=null): mixed, put(string $k, mixed $v): void, has(string $k): bool, forget(string $k): void, flash(string $k, mixed $v): void, pullFlash(string $k, mixed $d=null): mixed (okuma silmez, sonraki istekte düşer), regenerate(): void, destroy(): void, id(): string. Aktif oturum yoksa metotlar start() çağırır. Meta anahtarları: _created, _last, _ua, _flash_new, _flash_old.
app/Core/Csrf.php: static token(): string (64 hex, anahtar _csrf) | validate(?string $t): bool | field(): string.
app/Core/Validator.php: static make(array $data, array $rules, array $messages=[]): self | fails(): bool | errors(): array (alan => ilk mesaj) | validated(): array. messages: ‘alan.kural’, ‘kural’, ‘attributes’=>[alan=>etiket]. Kural dizi biçimi desteklenir (regex içinde ‘|’ için). Boş + boolean => false; boş + nullable/mevcut => null. phone_tr 05XXXXXXXXX’e, date Y-m-d veya Y-m-d H:i:s’e normalize eder; numeric string kalır.
app/Core/View.php: static share(string $k, mixed $v): void | render(string $name, array $data=[], ?string $layout=null): string | partial(string $name, array $data=[]): string. Ad deseni ^[a-z0-9_-]+(.[a-z0-9_-]+)*$.
app/Core/ErrorHandler.php: static register(): void | handle(Throwable $e, Request $r): Response. HttpException/genel hata JSON’da {“ok”:false,”error”:{code,message,errors:{}}}; ValidationException JSON 422, aksi halde flash errors+old ve 303 geri (aynı host Referer). View sırası: errors.error + layouts.main, sonra layoutsuz, sonra düz metin.
app/Core/Router.php: get|post|put|delete(string $path, array|callable $handler, array $opts=[]): void | group(array $attrs, callable $fn): void (prefix, middleware) | dispatch(Request $r): Response. 404/405 de global middleware’den geçer. Middleware alias sınıfı yoksa Sınıf.'Middleware' denenir. Varsayılan kısıtlar: {id}=\d+, {slug}=[a-z0-9]+(-[a-z0-9]+)*. int tipli parametre uyuşmazsa 404.
app/Core/Auth.php: static check(): bool, user(): ?array, id(): ?int, can(string $perm): bool, hasRole(string $r): bool, login(int $userId): void, logout(): void. Oturum anahtarı: auth_user_id (yalnızca id).
app/Core/RateLimiter.php: static tooManyAttempts(string $key, int $max, int $seconds): bool, hit(string $key, int $seconds): void, clear(string $key): void, availableIn(string $key): int.
app/Models/SecurityEvent.php [EK]: final static record(string $type, string $severity, ?int $userId, string $ip, array $detail=[]): void (yalnızca INSERT, hata yutulur).
app/Middleware/MiddlewareInterface.php: handle(Request $r, callable $next, array $args=[]): Response.
SecurityHeadersMiddleware / CsrfMiddleware / AuthMiddleware / GuestMiddleware / PermissionMiddleware / ThrottleMiddleware: final, handle(Request $r, callable $next, array $args=[]): Response. SecurityHeaders en dışta istisnaları ErrorHandler ile yanıta çevirir.
app/Controllers/BaseController.php: abstract, protected: view(string $name, array $d=[], string $layout='layouts.main'): Response, json(array $data, int $status=200): Response, ok(array $data=[]): Response, fail(string $code, string $msg, int $s=400, array $errors=[]): Response, redirect(string $url): Response, validate(Request $r, array $rules, array $messages=[]): array, flash(string $type, string $msg): void.
routes/web.php: GET / ve (APP_ENV=local) GET /_health. public/index.php: giriş noktası (sınıf yok); routes/web.php ve varsa routes/admin.php $router ile yüklenir.
(2) Tablolar ve permission kodları
sessions (DbSessionHandler; id=SHA-256, user_id, payload, ip_address, user_agent, last_activity)
users, roles, role_permissions, permissions (Auth, SELECT)
rate_limits (RateLimiter)
security_events (SecurityEvent: csrf_fail, permission_denied; yalnızca INSERT)
unique/exists kuralıyla çağıran tabloya göre herhangi bir tablo (ad ^[a-z0-9_]+$)
Permission kodu sabiti yok; PermissionMiddleware args’taki kodları Auth::can ile sorgular. GuestMiddleware yalnızca dashboard.view kullanır.
(3) JSON uç noktaları (hata biçimleri)
Tüm hatalar: {“ok”:false,”error”:{“code”:”...”,”message”:”...”,”errors”:{}}}
Kodlar: validation 422 (errors: alan=>mesaj), unauthenticated 401, forbidden 403, not_found 404, method_not_allowed 405, csrf 419, rate_limited 429 (+Retry-After), server_error 500.
GET /_health (local): {“ok”:true,”data”:{“status”:”up”,”db”:true,”time”:”...”}}
(4) ROUTE SATIRLARI
GET / -> Closure (geçici “Çekirdek çalışıyor”) | global
GET /_health -> Closure (yalnızca app.env=local) | global
Not: İlerideki rotalar $router->get('/yol', [Controller::class,'metot'], ['middleware'=>['auth','perm:product.create']]) biçiminde; throttle throttle:5,60.
(5) .env / settings anahtarları
app.* (config): app.timezone, app.debug, app.env, app.url, app.trusted_proxies, app.session.cookie_name / idle_minutes / absolute_minutes
security.csp, security.csrf_except, security.hsts; middleware.aliases, middleware.global
Oturum anahtarları (diğer oturumlar için): auth_user_id, intended_url (AuthMiddleware yazar, giriş sonrası controller okuyup siler), _csrf, flash ‘errors’ ve ‘old’.
settings tablosu kullanılmadı.
Notlar
storage/logs/php-error.log index.php’de error_log olarak ayarlanır.
Aynı kullanıcının oturumlarını sonlandırmak için sessions.user_id kullanılmalı (id özet olduğundan ham kimlikle eşleşmez).