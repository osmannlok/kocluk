<?php
/**
 * admin/auth.php
 * Bahar Yöresel - Admin Giriş İşlemi (Authentication)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/login.php');
    exit;
}

// CSRF token doğrulaması
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    $_SESSION['login_error'] = 'Geçersiz istek (CSRF doğrulaması başarısız). Lütfen tekrar deneyin.';
    header('Location: /admin/login.php?error=1');
    exit;
}

// Form verilerini al
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Boş alan kontrolü
if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Kullanıcı adı ve şifre boş bırakılamaz.';
    header('Location: /admin/login.php?error=1');
    exit;
}

try {
    // Kullanıcıyı veritabanında ara (prepared statement ile SQL injection koruması)
    $stmt = $db->prepare("SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $admin = $stmt->fetch();

    // Kullanıcı bulunamadıysa VEYA şifre eşleşmiyorsa
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        $_SESSION['login_error'] = 'Kullanıcı adı veya şifre hatalı.';
        header('Location: /admin/login.php?error=1');
        exit;
    }

    // Giriş başarılı - session bilgilerini ayarla
    session_regenerate_id(true); // Session sabitleme (session fixation) saldırılarına karşı
    $_SESSION['admin_id']       = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];

    header('Location: /admin/index.php');
    exit;

} catch (PDOException $e) {
    // Veritabanı hatası durumunda kullanıcıya detay gösterme, log tut
    error_log('Admin login DB error: ' . $e->getMessage());
    $_SESSION['login_error'] = 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
    header('Location: /admin/login.php?error=1');
    exit;
}