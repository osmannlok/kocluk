<?php
// app/Services/AuthService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Models\EmailVerification;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSession;
use PDOException;
use RuntimeException;

/**
 * Üyelik iş kuralları: kayıt, giriş, doğrulama, parola sıfırlama/değiştirme.
 */
final class AuthService
{
    private const VERIFY_HOURS = 24;
    private const RESET_MINUTES = 60;
    private const MAX_FAILS = 5;
    private const LOCK_MINUTES = 15;
    private const LOGIN_WINDOW_SECONDS = 900;
    private const IP_MAX_FAILS = 50;
    private const MAX_PASSWORD_LENGTH = 200;

    private const COMMON_PASSWORDS = [
        '12345678', '123456789', '1234567890', 'password', 'password1', 'password123', 'qwertyui',
        'qwerty123', 'qwertyuiop', 'iloveyou', '11111111', '00000000', 'abc12345', 'abcd1234',
        'admin123', 'welcome1', 'letmein1', 'sifre123', 'sifre1234', 'parola123', '87654321',
        '1q2w3e4r', '1qaz2wsx', 'q1w2e3r4', 'asdfghjk', 'asdf1234', 'zxcvbnm1', 'galatasaray',
        'fenerbahce', 'besiktas1', 'trabzonspor', 'istanbul34', 'ankara06', 'izmir3535', 'turkiye1',
        '123123123', '123456aa', 'aa123456', 'a1234567', '12345678a', 'passw0rd', 'p@ssw0rd',
        'monkey123', 'dragon123', 'football1', 'baseball1', 'superman1', 'master123', 'sunshine1',
        'princess1', 'shadow123', 'michael123', 'charlie123', '654321ab', '0123456789', '147258369',
        '159753456', '741852963', '987654321', '12341234', '11223344', '1234qwer', 'qwer1234',
    ];

    private User $users;
    private Role $roles;
    private LoginAttempt $attempts;
    private EmailVerification $verifications;
    private PasswordReset $resets;
    private UserSession $sessions;
    private MailService $mailer;

    public function __construct()
    {
        $this->users = new User();
        $this->roles = new Role();
        $this->attempts = new LoginAttempt();
        $this->verifications = new EmailVerification();
        $this->resets = new PasswordReset();
        $this->sessions = new UserSession();
        $this->mailer = new MailService();
    }

    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,user_id?:int,errors?:array<string,string>}
     */
    public function register(array $data, string $ipBin): array
    {
        // Mass assignment koruması: yalnızca bilinen alanlar tek tek okunur; role/status istekten ALINMAZ.
        $firstName = $this->cleanText($data['first_name'] ?? '', 80);
        $lastName = $this->cleanText($data['last_name'] ?? '', 80);
        $email = $this->normalizeEmail($data['email'] ?? '');
        $phone = $this->cleanText($data['phone'] ?? '', 20);
        $phone = $phone === '' ? null : $phone;
        $password = (string) ($data['password'] ?? '');
        $marketing = empty($data['marketing_consent']) ? 0 : 1;

        $errors = [];
        if ($firstName === '') {
            $errors['first_name'] = 'Ad zorunludur.';
        }
        if ($lastName === '') {
            $errors['last_name'] = 'Soyad zorunludur.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Geçerli bir e-posta adresi girin.';
        }
        $policy = $this->validatePasswordPolicy($password, $email);
        if ($policy !== []) {
            $errors['password'] = $policy[0];
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        // Neden önce hash: e-posta var/yok yollarının süresi birbirine yakın kalsın (kullanıcı varlığı sızmasın).
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $existing = $this->users->findByEmail($email);
        if ($existing !== null) {
            $this->notifyExistingAccount($existing, $email);
            return ['ok' => true];
        }

        $role = $this->roles->findByName('customer');
        if ($role === null) {
            throw new RuntimeException('customer rolü bulunamadı.');
        }

        try {
            /** @var array{0:int,1:string} $created */
            $created = Database::instance()->transaction(function () use ($role, $firstName, $lastName, $email, $phone, $hash, $marketing, $ipBin): array {
                $userId = $this->users->create([
                    'role_id' => (int) $role['id'],
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'password_hash' => $hash,
                    'status' => 'pending',
                    'kvkk_consent_at' => now(),
                    'marketing_consent' => $marketing,
                ]);

                return [$userId, $this->createVerificationToken($userId, $ipBin)];
            });
        } catch (PDOException $e) {
            // Eşzamanlı kayıt veya silinmiş hesabın e-postası: aynı genel sonuç, varlık sızmaz.
            if ($e->getCode() === '23000') {
                return ['ok' => true];
            }
            throw $e;
        }

        [$userId, $token] = $created;

        $this->mailer->send($email, 'E-posta adresinizi doğrulayın', 'verify', [
            'name' => $firstName,
            'url' => $this->absoluteUrl('/eposta-dogrula/' . $token),
            'hours' => self::VERIFY_HOURS,
        ]);

        AuditService::log('auth.register', 'user', $userId, null, ['email' => $email, 'role' => 'customer']);

        return ['ok' => true, 'user_id' => $userId];
    }

    /** @return array{ok:bool,code:string} */
    public function attemptLogin(string $email, string $password, string $ipBin, ?string $ua): array
    {
        $fail = ['ok' => false, 'code' => 'invalid_credentials'];
        $email = $this->normalizeEmail($email);
        $ip = bin_to_ip($ipBin);
        $ua = $ua !== null ? mb_substr($ua, 0, 255) : null;

        if ($email === '' || strlen($password) > self::MAX_PASSWORD_LENGTH) {
            $this->dummyVerify($password);
            return $fail;
        }

        // Neden e-posta+IP anahtarı: tek hesaba yönelik kaba kuvvet kısıtlanır, başkasının hesabı kilitlenmez.
        $key = 'login:' . hash('sha256', $email . '|' . $ip);

        if (
            RateLimiter::tooManyAttempts($key, self::MAX_FAILS, self::LOGIN_WINDOW_SECONDS)
            || $this->attempts->failedCountByIp($ipBin, 15) >= self::IP_MAX_FAILS
        ) {
            $this->attempts->record($email, $ipBin, $ua, false);
            return $fail;
        }

        $user = $this->users->findByEmail($email);
        $locked = $user !== null
            && $user['locked_until'] !== null
            && strtotime((string) $user['locked_until']) > time();
        $eligible = $user !== null && $user['status'] === 'active' && !$locked;

        if (!$eligible) {
            // Sabit zaman: kullanıcı yok/kilitli/askıda/pending ise de aynı maliyetli işlem yapılır; hata mesajı genel.
            $this->dummyVerify($password);
            $this->recordFailure($key, $email, $ipBin, $ua);
            return $fail;
        }

        $userId = (int) $user['id'];
        $hash = (string) $user['password_hash'];

        if (!password_verify($password, $hash)) {
            $this->recordFailure($key, $email, $ipBin, $ua);
            $count = $this->users->incrementFailed($userId);
            if ($count >= self::MAX_FAILS) {
                // Kilit bitince ilk hatada yeniden kilitlenir (sayaç sıfırlanmaz): kasıtlı sertleştirme.
                $this->users->lockUntil($userId, date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60));
                AuditService::security('login_lockout', 'warning', ['user_id' => $userId, 'ip' => $ip]);
            }
            return $fail;
        }

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            $this->users->update($userId, ['password_hash' => password_hash($password, PASSWORD_ARGON2ID)]);
        }

        $this->attempts->record($email, $ipBin, $ua, true);
        RateLimiter::clear($key);
        $this->users->resetFailed($userId);
        $this->users->recordLogin($userId, $ipBin);
        Auth::login($userId);

        AuditService::log('auth.login', 'user', $userId);

        return ['ok' => true, 'code' => 'ok'];
    }

    public function logout(): void
    {
        $userId = Auth::id();
        if ($userId !== null) {
            AuditService::log('auth.logout', 'user', $userId);
        }
        Auth::logout();
    }

    public function verifyEmail(string $token): bool
    {
        if (!$this->isValidToken($token)) {
            return false;
        }

        $row = $this->verifications->findValidByHash(hash('sha256', $token));
        if ($row === null) {
            return false;
        }

        $user = $this->users->find((int) $row['user_id']);
        if ($user === null || $user['status'] === 'suspended') {
            return false;
        }

        $userId = (int) $user['id'];

        Database::instance()->transaction(function () use ($row, $userId): void {
            $this->verifications->markUsed((int) $row['id']);
            $this->verifications->invalidateAllForUser($userId);
            $this->users->update($userId, ['email_verified_at' => now(), 'status' => 'active']);
        });

        AuditService::log('auth.email_verified', 'user', $userId);

        return true;
    }

    public function resendVerification(int $userId): bool
    {
        $user = $this->users->find($userId);
        if ($user === null || $user['email_verified_at'] !== null || $user['status'] === 'suspended') {
            return false;
        }

        $key = 'verify_resend:' . $userId;
        if (RateLimiter::tooManyAttempts($key, 3, 3600)) {
            return false;
        }
        RateLimiter::hit($key, 3600);

        $this->verifications->invalidateAllForUser($userId);
        $token = $this->createVerificationToken($userId, ip_to_bin(bin_to_ip(ip_to_bin((string) ($_SERVER['REMOTE_ADDR'] ?? '')) ?? '')) ?? '');

        $sent = $this->mailer->send((string) $user['email'], 'E-posta adresinizi doğrulayın', 'verify', [
            'name' => (string) $user['first_name'],
            'url' => $this->absoluteUrl('/eposta-dogrula/' . $token),
            'hours' => self::VERIFY_HOURS,
        ]);

        AuditService::log('auth.verification_resent', 'user', $userId);

        return $sent;
    }

    public function requestPasswordReset(string $email, string $ipBin): void
    {
        $email = $this->normalizeEmail($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $ip = bin_to_ip($ipBin);
        $key = 'pwreset:' . hash('sha256', $email . '|' . $ip);
        if (RateLimiter::tooManyAttempts($key, 3, 3600)) {
            return; // Sessizce çık: davranış kullanıcı olsun olmasın aynı.
        }
        RateLimiter::hit($key, 3600);

        $user = $this->users->findByEmail($email);
        if ($user === null || $user['status'] === 'suspended') {
            return;
        }

        $userId = (int) $user['id'];
        $this->resets->invalidateAllForUser($userId);

        $token = bin2hex(random_bytes(32)); // 64 hex; DB'de yalnızca SHA-256 hash'i
        $this->resets->issue(
            $userId,
            hash('sha256', $token),
            date('Y-m-d H:i:s', time() + self::RESET_MINUTES * 60),
            $ipBin
        );

        // NOT: SMTP süresi var/yok farkını ele verebilir; ileride kuyruğa alınmalı (v2).
        $this->mailer->send($email, 'Parola sıfırlama talebi', 'password-reset', [
            'name' => (string) $user['first_name'],
            'url' => $this->absoluteUrl('/sifre-sifirla/' . $token),
            'minutes' => self::RESET_MINUTES,
            'ip' => $ip,
        ]);

        AuditService::log('auth.password_reset_requested', 'user', $userId);
    }

    /** @return array{ok:bool,error?:string} */
    public function resetPassword(string $token, string $newPassword): array
    {
        $invalid = ['ok' => false, 'error' => 'Bağlantı geçersiz veya süresi dolmuş. Lütfen yeni bir sıfırlama isteyin.'];

        if (!$this->isValidToken($token)) {
            return $invalid;
        }

        $row = $this->resets->findValidByHash(hash('sha256', $token));
        if ($row === null) {
            return $invalid;
        }

        $user = $this->users->find((int) $row['user_id']);
        if ($user === null || $user['status'] === 'suspended') {
            return $invalid;
        }

        $errors = $this->validatePasswordPolicy($newPassword, (string) $user['email']);
        if ($errors !== []) {
            return ['ok' => false, 'error' => $errors[0]];
        }

        $userId = (int) $user['id'];
        $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $wasPending = $user['status'] === 'pending';

        Database::instance()->transaction(function () use ($row, $userId, $hash, $wasPending): void {
            $fields = ['password_hash' => $hash, 'failed_login_count' => 0, 'locked_until' => null];
            if ($wasPending) {
                // E-postaya gelen bağlantıyı kullanmak adres sahipliğini kanıtlar: hesap doğrulanmış sayılır.
                $fields['email_verified_at'] = now();
                $fields['status'] = 'active';
            }

            $this->users->update($userId, $fields);
            $this->resets->markUsed((int) $row['id']);
            $this->resets->invalidateAllForUser($userId);
            if ($wasPending) {
                $this->verifications->invalidateAllForUser($userId);
            }
            // Parola değişti: çalınmış olabilecek tüm oturumlar düşer.
            $this->sessions->deleteByUser($userId);
        });

        AuditService::log('auth.password_reset', 'user', $userId);

        $this->mailer->send((string) $user['email'], 'Parolanız değiştirildi', 'notice', [
            'name' => (string) $user['first_name'],
            'heading' => 'Parolanız değiştirildi',
            'paragraphs' => [
                'Hesabınızın parolası az önce sıfırlama bağlantısıyla değiştirildi. Tüm açık oturumlarınız sonlandırıldı.',
                'Bu işlemi siz yapmadıysanız lütfen hemen bizimle iletişime geçin.',
            ],
        ]);

        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public function changePassword(int $userId, string $current, string $new): array
    {
        $user = $this->users->find($userId);
        if ($user === null) {
            return ['ok' => false, 'error' => 'Kullanıcı bulunamadı.'];
        }

        $key = 'pwchange:' . $userId;
        if (RateLimiter::tooManyAttempts($key, 5, self::LOGIN_WINDOW_SECONDS)) {
            return ['ok' => false, 'error' => 'Çok fazla deneme yaptınız. Lütfen daha sonra tekrar deneyin.'];
        }

        $hash = (string) $user['password_hash'];
        if (strlen($current) > self::MAX_PASSWORD_LENGTH || !password_verify($current, $hash)) {
            RateLimiter::hit($key, self::LOGIN_WINDOW_SECONDS);
            return ['ok' => false, 'error' => 'Mevcut parolanız hatalı.'];
        }

        $errors = $this->validatePasswordPolicy($new, (string) $user['email']);
        if ($errors !== []) {
            return ['ok' => false, 'error' => $errors[0]];
        }

        if (password_verify($new, $hash)) {
            return ['ok' => false, 'error' => 'Yeni parola mevcut parolanızla aynı olamaz.'];
        }

        $this->users->update($userId, ['password_hash' => password_hash($new, PASSWORD_ARGON2ID)]);
        RateLimiter::clear($key);

        // Diğer cihazlardaki oturumlar kapanır; ardından bu oturumun kimliği yenilenir (yetki değişimi).
        $this->sessions->deleteByUser($userId, Session::id());
        Session::regenerate();

        AuditService::log('auth.password_changed', 'user', $userId);

        $this->mailer->send((string) $user['email'], 'Parolanız değiştirildi', 'notice', [
            'name' => (string) $user['first_name'],
            'heading' => 'Parolanız değiştirildi',
            'paragraphs' => [
                'Hesabınızın parolası değiştirildi. Diğer cihazlardaki oturumlarınız sonlandırıldı.',
                'Bu işlemi siz yapmadıysanız lütfen hemen bizimle iletişime geçin.',
            ],
        ]);

        return ['ok' => true];
    }

    /** @return list<string> Hata mesajları; boş dizi = politika sağlandı. */
    public function validatePasswordPolicy(string $pw, string $email): array
    {
        $errors = [];
        $length = mb_strlen($pw);

        if ($length < 8) {
            $errors[] = 'Parola en az 8 karakter olmalıdır.';
        }
        if (strlen($pw) > self::MAX_PASSWORD_LENGTH) {
            $errors[] = 'Parola en fazla ' . self::MAX_PASSWORD_LENGTH . ' karakter olabilir.';
        }

        $email = mb_strtolower(trim($email));
        if ($email !== '' && $length > 0) {
            $lowerPw = mb_strtolower($pw);
            $local = explode('@', $email)[0];
            if (str_contains($lowerPw, $email) || (mb_strlen($local) >= 4 && str_contains($lowerPw, $local))) {
                $errors[] = 'Parola e-posta adresinizi içermemelidir.';
            }
        }

        if ($length > 0 && (preg_match('/^\d+$/', $pw) === 1 || preg_match('/^\p{L}+$/u', $pw) === 1)) {
            $errors[] = 'Parola yalnızca rakamlardan veya yalnızca harflerden oluşamaz.';
        }

        if (in_array(mb_strtolower($pw), self::COMMON_PASSWORDS, true)) {
            $errors[] = 'Bu parola çok yaygın; lütfen daha özgün bir parola seçin.';
        }

        return $errors;
    }

    private function recordFailure(string $key, string $email, string $ipBin, ?string $ua): void
    {
        $this->attempts->record($email, $ipBin, $ua, false);
        RateLimiter::hit($key, self::LOGIN_WINDOW_SECONDS);
    }

    /** Sahte doğrulama: gerçek parola kontrolüyle benzer maliyet (zamanlama farkından kullanıcı varlığı sızmasın). */
    private function dummyVerify(string $password): void
    {
        password_hash($password === '' ? 'x' : substr($password, 0, self::MAX_PASSWORD_LENGTH), PASSWORD_ARGON2ID);
    }

    private function createVerificationToken(int $userId, string $ipBin): string
    {
        $token = bin2hex(random_bytes(32)); // 64 hex; DB'de yalnızca SHA-256 hash'i
        $this->verifications->issue(
            $userId,
            hash('sha256', $token),
            date('Y-m-d H:i:s', time() + self::VERIFY_HOURS * 3600),
            $ipBin !== '' ? $ipBin : null
        );

        return $token;
    }

    /** @param array<string,mixed> $existing */
    private function notifyExistingAccount(array $existing, string $email): void
    {
        // E-posta bombardımanını önle: aynı adrese saatte en fazla 1 bildirim.
        $key = 'reg_exists:' . hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 1, 3600)) {
            return;
        }
        RateLimiter::hit($key, 3600);

        $this->mailer->send($email, 'Zaten bir hesabınız var', 'notice', [
            'name' => (string) $existing['first_name'],
            'heading' => 'Zaten bir hesabınız var',
            'paragraphs' => [
                'Bu e-posta adresiyle yeni bir kayıt denemesi yapıldı; ancak adresinize ait bir hesap zaten mevcut.',
                'Giriş yapabilirsiniz. Parolanızı hatırlamıyorsanız giriş sayfasındaki "Şifremi unuttum" bağlantısını kullanın. Bu denemeyi siz yapmadıysanız bu e-postayı yok sayabilirsiniz.',
            ],
            'buttonText' => 'Giriş Yap',
            'buttonUrl' => $this->absoluteUrl('/giris'),
        ]);
    }

    private function isValidToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    private function normalizeEmail(mixed $value): string
    {
        $email = mb_strtolower(trim((string) $value));

        return mb_strlen($email) > 190 ? '' : $email;
    }

    private function cleanText(mixed $value, int $max): string
    {
        $text = preg_replace('/\s+/u', ' ', (string) $value) ?? '';

        return mb_substr(trim($text), 0, $max);
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim((string) Config::get('app.url', ''), '/') . $path;
    }
}