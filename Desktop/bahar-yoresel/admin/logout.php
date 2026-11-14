<?php
/**
 * admin/logout.php
 * Bahar Yöresel - Admin Çıkış İşlemi
 */

require_once __DIR__ . '/../config/session.php';

// Session değişkenlerini temizle
$_SESSION = [];

// Session cookie'sini de temizle (tarayıcıdan sil)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Session'ı tamamen yok et
session_destroy();

// Login sayfasına yönlendir
header('Location: /admin/login.php');
exit;