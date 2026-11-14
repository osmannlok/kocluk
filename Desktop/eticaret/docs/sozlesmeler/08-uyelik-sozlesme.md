# 08-uyelik-sozlesme.md
Oturum 5: Üyelik. Kaynak: yazılan GERÇEK kod. Modeller instance çağrılır. Sayfaya özel JS: Controller View::share('scripts', ['auth.js']) kullanır (View::render yalnızca $content'i layout'a garanti eder; settings/cartCount da share ile gelir). Layout $scripts/$styles'ı View'dan değişken olarak okur.

## (1) Dosya, sınıf, public metot imzaları
- app/Models/UserSession.php: final UserSession extends BaseModel (sessions) | deleteByUser(int $userId, ?string $exceptSessionId=null): int (except hem ham hem SHA-256 hariç tutar) | countByUser(int $userId): int. Görev: kullanıcı bazlı oturum silme/sayım. [Yeni model: UserSession]
- resources/views/layouts/main.php DEĞİŞİKLİK: head'e $styles, body sonuna $scripts döngüsü (regex ^[a-z0-9._-]+\.(css|js)$, asset('css/..'|'js/..'), e()).
- app/Services/AuditService.php: final | static log(string $action, ?string $entityType=null, ?int $entityId=null, ?array $old=null, ?array $new=null): void | static security(string $type, string $severity, array $detail=[]): void. Görev: maskeli denetim/güvenlik olayı; hata yutulur.
- app/Services/MailService.php: final | send(string $to, string $subject, string $template, array $data=[]): bool. Görev: PHPMailer (smtp) veya storage/mail/*.html (log); View 'emails.<ad>' + 'layouts.email'.
- resources/views/layouts/email.php; emails/verify.php ($name,$url,$hours); emails/password-reset.php ($name,$url,$minutes,$ip); emails/notice.php [EK] ($name,$heading,$paragraphs[,$buttonText,$buttonUrl]).
- app/Services/AuthService.php: final | __construct() | register(array $data, string $ipBin): array{ok:bool,user_id?:int,errors?:array<string,string>} | attemptLogin(string $email, string $password, string $ipBin, ?string $ua): array{ok:bool,code:string} | logout(): void | verifyEmail(string $token): bool | resendVerification(int $userId): bool | requestPasswordReset(string $email, string $ipBin): void | resetPassword(string $token, string $newPassword): array{ok:bool,error?:string} | changePassword(int $userId, string $current, string $new): array{ok:bool,error?:string} | validatePasswordPolicy(string $pw, string $email): list<string>. Görev: üyelik iş kuralları.
- app/Controllers/Front/AuthController.php: final AuthController extends BaseController | showLogin(Request): Response | login(Request): Response | showRegister(Request): Response | register(Request): Response | logout(Request): Response | verifyEmail(Request, string $token): Response | showVerifyNotice(Request): Response | resendVerification(Request): Response | showForgot(Request): Response | forgot(Request): Response | showReset(Request, string $token): Response | reset(Request): Response.
- resources/views/front/auth/: login ($title,$metaDescription), register (+$legalLinks{kvkk,terms}), forgot, reset (+$token), verify-notice (+$email,$verified,$canResend).
- public/assets/js/auth.js: öznitelikler [data-password-toggle], [data-strength-meter][data-target][data-email], [data-match-with][data-match-message], form[data-auth-form][data-loading-text].
- scripts/create_admin.php: yalnızca CLI; seçenekler --first --last --email --password; ikinci admin için 'onaylıyorum'.

## (2) Tablolar ve permission kodları
Tablolar (model üzerinden): users, roles, email_verifications, password_resets, login_attempts, sessions, audit_logs, security_events, pages (yalnızca findBySystemKey), settings (mail.from_name), rate_limits (RateLimiter).
Permission: dashboard.view (login sonrası /yonetim yönlendirmesi, can()).
Güvenlik olayı türleri: login_lockout (warning), register_honeypot (info). Audit eylemleri: auth.register, auth.login, auth.logout, auth.email_verified, auth.verification_resent, auth.password_reset_requested, auth.password_reset, auth.password_changed, user.create_admin_cli.
RateLimiter anahtarları: login:<sha256>, pwreset:<sha256>, reg_exists:<sha256>, verify_resend:<id>, pwchange:<id>.

## (3) JSON uç noktaları
YOK (form/redirect akışı). Doğrulama hatası: ErrorHandler flash errors+old ve geri yönlendirme (AJAX'ta 422 şeması).

## (4) ROUTE SATIRLARI
GET /giris -> AuthController@showLogin | guest
POST /giris -> AuthController@login | guest, throttle:10,60
GET /kayit -> AuthController@showRegister | guest
POST /kayit -> AuthController@register | guest, throttle:5,60
POST /cikis -> AuthController@logout | auth
GET /eposta-dogrula -> AuthController@showVerifyNotice | auth
GET /eposta-dogrula/{token} -> AuthController@verifyEmail | global
POST /eposta-dogrula/tekrar -> AuthController@resendVerification | auth, throttle:3,60
GET /sifremi-unuttum -> AuthController@showForgot | guest
POST /sifremi-unuttum -> AuthController@forgot | guest, throttle:5,60
GET /sifre-sifirla/{token} -> AuthController@showReset | guest
POST /sifre-sifirla -> AuthController@reset | guest, throttle:5,60

## (5) .env / settings anahtarları
.env/config: app.url, app.name, app.mail.driver|host|port|user|pass|encryption|from_address (MAIL_DRIVER=log|smtp).
settings: mail.from_name. Oturum anahtarları: intended_url (okunup silinir), flash old/errors, flash türleri success|danger|info.

## Notlar (sonraki oturumlar)
- Auth::check status≠active oturumu düşürdüğünden pending kullanıcı giriş yapamaz: /eposta-dogrula ve /eposta-dogrula/tekrar [auth] pending'e ulaşmaz; kayıt sonrası /giris'e yönlenir; başarılı parola sıfırlama pending hesabı aktifleştirir. Route'ların guest+throttle yapılması önerilir.
- Misafir sepeti birleştirmesi AuthService/AuthController'da ÇAĞRILMAZ; Oturum 7'de CartService login sonrası bağlanmalı.
- Register/legal bağlantıları pages.system_key kvkk ve distance_sales slug'ından çözülür.
- changePassword hesap ekranı controller'ı (Oturum 6/7) tarafından çağrılır: deleteByUser(except Session::id()) + Session::regenerate() serviste yapılır.
- Composer: phpmailer/phpmailer gerekli; composer autoload.files functions.php içermeli.