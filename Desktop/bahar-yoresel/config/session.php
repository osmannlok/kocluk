<?php
/**
 * config/session.php
 * Bahar Yöresel - Oturum Yönetimi, CSRF Koruması ve Admin Giriş Kontrolü
 */

// Session güvenlik ayarları (session_start() çağrılmadan ÖNCE ayarlanmalı)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);   // JS ile cookie'ye erişimi engelle (XSS koruması)
    ini_set('session.use_only_cookies', 1);  // Session ID'nin URL'de taşınmasını engelle
    ini_set('session.cookie_samesite', 'Lax'); // CSRF riskini azaltır

    // HTTPS kullanılıyorsa cookie sadece HTTPS üzerinden gönderilsin
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }

    session_start();
}

/**
 * CSRF token oluşturur ve session'a kaydeder.
 * Form render edilirken çağrılır, token bir hidden input olarak forma eklenir.
 *
 * @return string Üretilen CSRF token
 */
function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Formdan gelen CSRF token'ı session'daki token ile karşılaştırır.
 * timing attack'lara karşı hash_equals() kullanılır.
 *
 * @param string $token Formdan/POST'tan gelen token
 * @return bool Doğrulama sonucu
 */
function validateCSRFToken(string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Admin girişi yapılmış mı kontrol eder.
 * Yapılmamışsa login sayfasına yönlendirir ve script'i sonlandırır.
 *
 * @return void
 */
function checkAdminLogin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * (Yardımcı) Admin giriş yapmış mı, sadece bool döner - yönlendirme yapmaz.
 * Sayfa içinde koşullu gösterim gerektiğinde kullanılabilir.
 *
 * @return bool
 */
function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}