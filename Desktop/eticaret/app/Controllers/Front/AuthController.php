<?php
// app/Controllers/Front/AuthController.php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\View;
use App\Models\Page;
use App\Services\AuditService;
use App\Services\AuthService;

final class AuthController extends BaseController
{
    private ?AuthService $service = null;

    public function showLogin(Request $request): Response
    {
        $this->useAuthAssets();

        return $this->view('front.auth.login', [
            'title' => 'Giriş Yap',
            'metaDescription' => 'Hesabınıza giriş yapın.',
        ]);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email|max:190',
            'password' => 'required|string|max:200',
        ], ['attributes' => ['email' => 'E-posta', 'password' => 'Parola']]);

        $result = $this->service()->attemptLogin(
            (string) $data['email'],
            (string) $data['password'],
            $this->ipBin($request),
            $request->userAgent()
        );

        if (!$result['ok']) {
            // Mesaj genel: hesabın var/yok/kilitli/doğrulanmamış olduğu ayırt edilemez.
            Session::flash('old', ['email' => (string) $data['email']]);
            $this->flash('danger', 'E-posta veya parola hatalı ya da hesabınız henüz doğrulanmamış olabilir.');

            return $this->redirect('/giris');
        }

        $target = $this->safeIntended();
        if ($target === null) {
            $target = can('dashboard.view') ? '/yonetim' : '/hesabim';
        }

        return $this->redirect($target);
    }

    public function showRegister(Request $request): Response
    {
        $this->useAuthAssets();

        return $this->view('front.auth.register', [
            'title' => 'Üye Ol',
            'metaDescription' => 'Yöresel ürünler için yeni hesap oluşturun.',
            'legalLinks' => $this->legalLinks(),
        ]);
    }

    public function register(Request $request): Response
    {
        // Honeypot: botlar gizli alanı doldurur; sessizce başarılı gibi davranılır.
        if (trim((string) $request->post('website', '')) !== '') {
            AuditService::security('register_honeypot', 'info');
            $this->flash('success', $this->registerNotice());

            return $this->redirect('/giris');
        }

        $data = $this->validate($request, [
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|phone_tr',
            'password' => 'required|string|min:8|max:200|confirmed',
            'marketing_consent' => 'nullable|boolean',
        ], ['attributes' => [
            'first_name' => 'Ad',
            'last_name' => 'Soyad',
            'email' => 'E-posta',
            'phone' => 'Telefon',
            'password' => 'Parola',
        ]]);

        $old = [
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
        ];

        $consentErrors = [];
        if ((string) $request->post('kvkk', '') !== '1') {
            $consentErrors['kvkk'] = 'KVKK aydınlatma metnini onaylamanız gerekir.';
        }
        if ((string) $request->post('terms', '') !== '1') {
            $consentErrors['terms'] = 'Üyelik ve mesafeli satış sözleşmesini onaylamanız gerekir.';
        }
        if ($consentErrors !== []) {
            throw new ValidationException($consentErrors, $old);
        }

        // Servise yalnızca açıkça seçilmiş alanlar verilir (alan beyaz listesi).
        $result = $this->service()->register([
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'] ?? '',
            'marketing_consent' => !empty($data['marketing_consent']),
        ], $this->ipBin($request));

        if (!$result['ok']) {
            throw new ValidationException($result['errors'] ?? ['email' => 'Kayıt tamamlanamadı.'], $old);
        }

        // Mesaj ve yönlendirme e-posta var/yok durumundan bağımsız aynıdır.
        $this->flash('success', $this->registerNotice());

        return $this->redirect('/giris');
    }

    public function logout(Request $request): Response
    {
        $this->service()->logout();
        $this->flash('success', 'Çıkış yaptınız.');

        return $this->redirect('/');
    }

    public function verifyEmail(Request $request, string $token): Response
    {
        if ($this->service()->verifyEmail($token)) {
            $this->flash('success', 'E-posta adresiniz doğrulandı. Giriş yapabilirsiniz.');

            return $this->redirect('/giris');
        }

        $this->flash('danger', 'Doğrulama bağlantısı geçersiz veya süresi dolmuş.');

        return $this->redirect(Auth::check() ? '/eposta-dogrula' : '/giris');
    }

    public function showVerifyNotice(Request $request): Response
    {
        $this->useAuthAssets();
        $user = Auth::user();

        return $this->view('front.auth.verify-notice', [
            'title' => 'E-postanızı Doğrulayın',
            'metaDescription' => 'E-posta adresinizi doğrulayın.',
            'email' => $user['email'] ?? null,
            'verified' => $user !== null && $user['email_verified_at'] !== null,
            'canResend' => $user !== null,
        ]);
    }

    public function resendVerification(Request $request): Response
    {
        $userId = Auth::id();
        if ($userId === null) {
            return $this->redirect('/giris');
        }

        if ($this->service()->resendVerification($userId)) {
            $this->flash('success', 'Doğrulama e-postası gönderildi. Lütfen gelen kutunuzu kontrol edin.');
        } else {
            $this->flash('info', 'Doğrulama e-postası gönderilemedi veya e-posta adresiniz zaten doğrulanmış.');
        }

        return $this->redirect('/eposta-dogrula');
    }

    public function showForgot(Request $request): Response
    {
        $this->useAuthAssets();

        return $this->view('front.auth.forgot', [
            'title' => 'Şifremi Unuttum',
            'metaDescription' => 'Parolanızı sıfırlamak için e-posta adresinizi girin.',
        ]);
    }

    public function forgot(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email|max:190',
        ], ['attributes' => ['email' => 'E-posta']]);

        $this->service()->requestPasswordReset((string) $data['email'], $this->ipBin($request));

        // Her durumda aynı bilgilendirme: hesabın varlığı sızmaz.
        $this->flash('success', 'Bu e-posta adresiyle kayıtlı bir hesap varsa parola sıfırlama bağlantısı gönderildi.');

        return $this->redirect('/sifremi-unuttum');
    }

    public function showReset(Request $request, string $token): Response
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            $this->flash('danger', 'Sıfırlama bağlantısı geçersiz. Lütfen yeni bir talep oluşturun.');

            return $this->redirect('/sifremi-unuttum');
        }

        $this->useAuthAssets();

        return $this->view('front.auth.reset', [
            'title' => 'Yeni Parola Belirle',
            'metaDescription' => 'Hesabınız için yeni bir parola belirleyin.',
            'token' => $token,
        ]);
    }

    public function reset(Request $request): Response
    {
        $data = $this->validate($request, [
            'token' => 'required|string|regex:/^[a-f0-9]{64}$/',
            'password' => 'required|string|min:8|max:200|confirmed',
        ], ['attributes' => ['token' => 'Bağlantı', 'password' => 'Parola']]);

        $token = (string) $data['token'];
        $result = $this->service()->resetPassword($token, (string) $data['password']);

        if (!$result['ok']) {
            $this->flash('danger', $result['error'] ?? 'Parola sıfırlanamadı.');

            return $this->redirect('/sifre-sifirla/' . $token);
        }

        $this->flash('success', 'Parolanız güncellendi. Yeni parolanızla giriş yapabilirsiniz.');

        return $this->redirect('/giris');
    }

    private function service(): AuthService
    {
        return $this->service ??= new AuthService();
    }

    private function useAuthAssets(): void
    {
        View::share('scripts', ['auth.js']);
    }

    private function ipBin(Request $request): string
    {
        return ip_to_bin($request->ip()) ?? "\0\0\0\0";
    }

    private function registerNotice(): string
    {
        return 'Kayıt işleminiz alındı. E-posta kutunuzu kontrol edip doğrulama bağlantısına tıklayarak hesabınızı etkinleştirin.';
    }

    /** Açık yönlendirmeyi önler: yalnızca aynı site göreli yolu kabul edilir. */
    private function safeIntended(): ?string
    {
        $path = Session::get('intended_url');
        Session::forget('intended_url');

        if (!is_string($path) || $path === '' || $path[0] !== '/') {
            return null;
        }
        if (str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return null;
        }
        foreach (['/giris', '/kayit', '/cikis'] as $blocked) {
            if (str_starts_with($path, $blocked)) {
                return null;
            }
        }

        return $path;
    }

    /** @return array{kvkk:string,terms:string} */
    private function legalLinks(): array
    {
        $pages = new Page();
        $kvkk = $pages->findBySystemKey('kvkk');
        $terms = $pages->findBySystemKey('distance_sales');

        return [
            'kvkk' => $kvkk !== null ? '/sayfa/' . $kvkk['slug'] : '/sayfa/kvkk',
            'terms' => $terms !== null ? '/sayfa/' . $terms['slug'] : '/sayfa/mesafeli-satis-sozlesmesi',
        ];
    }
}