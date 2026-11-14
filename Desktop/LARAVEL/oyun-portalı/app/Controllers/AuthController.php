<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;

class AuthController
{
    public function loginForm(): void
    {
        $error = $_SESSION['auth_error'] ?? null;
        unset($_SESSION['auth_error']);

        $title = 'Giriş Yap - Eğitim Oyun Portalı';
        $description = 'Hesabınıza giriş yapın ve öğrenme oyunlarına devam edin.';

        require BASE_PATH . '/views/auth/login.php';
    }

    public function login(): void
    {
        if (!$this->verifyCsrf()) {
            $_SESSION['auth_error'] = 'Geçersiz istek. Lütfen tekrar deneyin.';
            $this->redirect('/giris');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $_SESSION['auth_error'] = 'E-posta ve şifre zorunludur.';
            $this->redirect('/giris');
        }

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password'])) {
            $_SESSION['auth_error'] = 'E-posta veya şifre hatalı.';
            $this->redirect('/giris');
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_id' => $user['role_id'],
        ];

        $this->redirect('/');
    }

    public function registerForm(): void
    {
        $error = $_SESSION['auth_error'] ?? null;
        unset($_SESSION['auth_error']);

        $title = 'Kayıt Ol - Eğitim Oyun Portalı';
        $description = 'Ücretsiz hesap oluşturun ve oyunlara hemen başlayın.';

        require BASE_PATH . '/views/auth/register.php';
    }

    public function register(): void
    {
        if (!$this->verifyCsrf()) {
            $_SESSION['auth_error'] = 'Geçersiz istek. Lütfen tekrar deneyin.';
            $this->redirect('/kayit');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            $_SESSION['auth_error'] = 'Tüm alanları doldurun.';
            $this->redirect('/kayit');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['auth_error'] = 'Geçerli bir e-posta girin.';
            $this->redirect('/kayit');
        }

        if (strlen($password) < 8) {
            $_SESSION['auth_error'] = 'Şifre en az 8 karakter olmalıdır.';
            $this->redirect('/kayit');
        }

        if ($password !== $passwordConfirm) {
            $_SESSION['auth_error'] = 'Şifreler eşleşmiyor.';
            $this->redirect('/kayit');
        }

        $userModel = new User();

        if ($userModel->emailExists($email)) {
            $_SESSION['auth_error'] = 'Bu e-posta ile zaten bir hesap var.';
            $this->redirect('/kayit');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userId = $userModel->create($name, $email, $passwordHash);

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role_id' => 2,
        ];

        $this->redirect('/');
    }

    public function logout(): void
    {
        $cookieName = session_name();

        $_SESSION = [];

        if ($cookieName !== '' && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie($cookieName, '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        $this->redirect('/');
    }

    private function verifyCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';

        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}