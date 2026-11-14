<?php
/**
 * admin/campaign_save.php
 * Bahar Yöresel - Yeni Kampanya Kaydetme İşlemi
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/campaign_add.php');
    exit;
}

// --- CSRF DOĞRULAMA ---
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    $_SESSION['form_error'] = 'Geçersiz istek (CSRF doğrulaması başarısız).';
    header('Location: /admin/campaign_add.php?error=1');
    exit;
}

// --- FORM VERİLERİNİ AL ---
$title         = trim($_POST['title'] ?? '');
$description   = trim($_POST['description'] ?? '');
$discountType  = $_POST['discount_type'] ?? '';
$discountValue = $_POST['discount_value'] ?? '';
$startDate     = trim($_POST['start_date'] ?? '');
$endDate       = trim($_POST['end_date'] ?? '');
$isActive      = isset($_POST['is_active']) ? 1 : 0; // checkbox işaretlenmemişse POST edilmez

/**
 * Bir tarih string'inin YYYY-MM-DD formatında ve geçerli bir tarih olup olmadığını kontrol eder.
 */
function gecerliTarihMi(string $tarih): bool
{
    if ($tarih === '') {
        return true; // Boş tarih izinli (opsiyonel alan)
    }
    $d = DateTime::createFromFormat('Y-m-d', $tarih);
    return $d && $d->format('Y-m-d') === $tarih;
}

// --- VALIDATION ---
$hatalar = [];

if ($title === '') {
    $hatalar[] = 'Kampanya başlığı boş bırakılamaz.';
}

$gecerliTurler = ['percentage', 'fixed', 'free_shipping'];
if (!in_array($discountType, $gecerliTurler, true)) {
    $hatalar[] = 'Geçerli bir indirim türü seçiniz.';
}

// discount_value: free_shipping için opsiyonel, diğerleri için zorunlu ve sayısal
if ($discountType !== 'free_shipping') {
    if ($discountValue === '' || !is_numeric($discountValue) || (float) $discountValue < 0) {
        $hatalar[] = 'Geçerli bir indirim değeri giriniz.';
    }
} else {
    // free_shipping seçiliyse değer her zaman NULL olarak kaydedilecek
    $discountValue = '';
}

if (!gecerliTarihMi($startDate)) {
    $hatalar[] = 'Başlangıç tarihi formatı geçersiz (YYYY-MM-DD olmalı).';
}

if (!gecerliTarihMi($endDate)) {
    $hatalar[] = 'Bitiş tarihi formatı geçersiz (YYYY-MM-DD olmalı).';
}

if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    $hatalar[] = 'Bitiş tarihi, başlangıç tarihinden önce olamaz.';
}

if (!empty($hatalar)) {
    $_SESSION['form_error'] = implode(' ', $hatalar);
    header('Location: /admin/campaign_add.php?error=1');
    exit;
}

// --- VERİTABANINA KAYDET ---
try {
    $stmt = $db->prepare("
        INSERT INTO campaigns (title, description, discount_type, discount_value, start_date, end_date, is_active)
        VALUES (:title, :description, :discount_type, :discount_value, :start_date, :end_date, :is_active)
    ");

    $stmt->execute([
        'title'          => $title,
        'description'    => $description !== '' ? $description : null,
        'discount_type'  => $discountType,
        'discount_value' => $discountValue !== '' ? (float) $discountValue : null,
        'start_date'     => $startDate !== '' ? $startDate : null,
        'end_date'       => $endDate !== '' ? $endDate : null,
        'is_active'      => $isActive,
    ]);

    header('Location: /admin/campaigns.php?success=1');
    exit;

} catch (PDOException $e) {
    error_log('Kampanya kaydetme hatası: ' . $e->getMessage());
    $_SESSION['form_error'] = 'Kampanya kaydedilirken bir veritabanı hatası oluştu.';
    header('Location: /admin/campaign_add.php?error=1');
    exit;
}