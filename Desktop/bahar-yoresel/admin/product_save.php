<?php
/**
 * admin/product_save.php
 * Bahar Yöresel - Yeni Ürün Kaydetme İşlemi
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/product_add.php');
    exit;
}

// --- CSRF DOĞRULAMA ---
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    $_SESSION['form_error'] = 'Geçersiz istek (CSRF doğrulaması başarısız).';
    header('Location: /admin/product_add.php?error=1');
    exit;
}

// --- FORM VERİLERİNİ AL ---
$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$categoryId  = $_POST['category_id'] ?? '';
$price       = $_POST['price'] ?? '';
$stock       = $_POST['stock'] ?? '';

// --- VALIDATION ---
$hatalar = [];

if ($name === '') {
    $hatalar[] = 'Ürün adı boş bırakılamaz.';
}

if ($price === '' || !is_numeric($price) || (float) $price < 0) {
    $hatalar[] = 'Geçerli bir fiyat giriniz.';
}

if ($stock === '' || !ctype_digit((string) $stock) && !is_numeric($stock)) {
    $hatalar[] = 'Stok adedi sayısal olmalıdır.';
} elseif ((int) $stock < 0) {
    $hatalar[] = 'Stok adedi negatif olamaz.';
}

// category_id boşsa NULL yap, doluysa sayısal olmalı
if ($categoryId === '' || $categoryId === null) {
    $categoryId = null;
} elseif (!ctype_digit((string) $categoryId)) {
    $hatalar[] = 'Geçersiz kategori seçimi.';
}

if (!empty($hatalar)) {
    $_SESSION['form_error'] = implode(' ', $hatalar);
    header('Location: /admin/product_add.php?error=1');
    exit;
}

// --- GÖRSEL YÜKLEME (basit, tek dosya) ---
$imagePath = null;

if (isset($_FILES['images']) && $_FILES['images']['error'] === UPLOAD_ERR_OK) {

    $izinliTipler = ['image/jpeg', 'image/png', 'image/webp'];
    $dosyaTipi    = mime_content_type($_FILES['images']['tmp_name']);

    if (!in_array($dosyaTipi, $izinliTipler, true)) {
        $_SESSION['form_error'] = 'Sadece JPG, PNG veya WEBP formatında görsel yükleyebilirsiniz.';
        header('Location: /admin/product_add.php?error=1');
        exit;
    }

    $maxBoyut = 5 * 1024 * 1024; // 5 MB
    if ($_FILES['images']['size'] > $maxBoyut) {
        $_SESSION['form_error'] = 'Görsel boyutu 5 MB\'ı aşamaz.';
        header('Location: /admin/product_add.php?error=1');
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Benzersiz dosya adı üret (orijinal dosya adını güvenmeden)
    $uzanti      = pathinfo($_FILES['images']['name'], PATHINFO_EXTENSION);
    $dosyaAdi    = uniqid('urun_', true) . '.' . strtolower($uzanti);
    $hedefYol    = $uploadDir . $dosyaAdi;

    if (!move_uploaded_file($_FILES['images']['tmp_name'], $hedefYol)) {
        $_SESSION['form_error'] = 'Görsel yüklenirken bir hata oluştu.';
        header('Location: /admin/product_add.php?error=1');
        exit;
    }

    $imagePath = $dosyaAdi;
}

// --- VERİTABANINA KAYDET ---
try {
    $stmt = $db->prepare("
        INSERT INTO products (name, description, category_id, price, stock, images)
        VALUES (:name, :description, :category_id, :price, :stock, :images)
    ");

    $stmt->execute([
        'name'        => $name,
        'description' => $description,
        'category_id' => $categoryId,
        'price'       => (float) $price,
        'stock'       => (int) $stock,
        'images'      => $imagePath ? json_encode([$imagePath]) : null,
    ]);

    header('Location: /admin/products.php?success=1');
    exit;

} catch (PDOException $e) {
    error_log('Ürün kaydetme hatası: ' . $e->getMessage());
    $_SESSION['form_error'] = 'Ürün kaydedilirken bir veritabanı hatası oluştu.';
    header('Location: /admin/product_add.php?error=1');
    exit;
}