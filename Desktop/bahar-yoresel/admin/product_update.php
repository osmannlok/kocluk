<?php
/**
 * admin/product_update.php
 * Bahar Yöresel - Ürün Güncelleme İşlemi
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/products.php');
    exit;
}

// --- ID KONTROLÜ ---
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    header('Location: /admin/products.php');
    exit;
}

// --- CSRF DOĞRULAMA ---
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    $_SESSION['form_error'] = 'Geçersiz istek (CSRF doğrulaması başarısız).';
    header("Location: /admin/product_edit.php?id={$id}&error=1");
    exit;
}

// --- ÜRÜNÜN MEVCUT HALİNİ ÇEK (eski görseli silmek/karşılaştırmak için) ---
try {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $mevcutUrun = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Ürün getirme hatası: ' . $e->getMessage());
    $mevcutUrun = false;
}

if (!$mevcutUrun) {
    header('Location: /admin/products.php');
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

if ($stock === '' || !is_numeric($stock)) {
    $hatalar[] = 'Stok adedi sayısal olmalıdır.';
} elseif ((int) $stock < 0) {
    $hatalar[] = 'Stok adedi negatif olamaz.';
}

if ($categoryId === '' || $categoryId === null) {
    $categoryId = null;
} elseif (!ctype_digit((string) $categoryId)) {
    $hatalar[] = 'Geçersiz kategori seçimi.';
}

if (!empty($hatalar)) {
    $_SESSION['form_error'] = implode(' ', $hatalar);
    header("Location: /admin/product_edit.php?id={$id}&error=1");
    exit;
}

// --- MEVCUT GÖRSELİ ÇÖZ ---
$mevcutGorselDosyasi = null;
if (!empty($mevcutUrun['images'])) {
    $gorselDizisi = json_decode($mevcutUrun['images'], true);
    if (is_array($gorselDizisi) && !empty($gorselDizisi)) {
        $mevcutGorselDosyasi = $gorselDizisi[0];
    }
}

// Varsayılan olarak eski görsel korunur
$imagePath = $mevcutGorselDosyasi;

// --- YENİ GÖRSEL YÜKLENDİYSE İŞLE ---
if (isset($_FILES['images']) && $_FILES['images']['error'] === UPLOAD_ERR_OK) {

    $izinliTipler = ['image/jpeg', 'image/png', 'image/webp'];
    $dosyaTipi    = mime_content_type($_FILES['images']['tmp_name']);

    if (!in_array($dosyaTipi, $izinliTipler, true)) {
        $_SESSION['form_error'] = 'Sadece JPG, PNG veya WEBP formatında görsel yükleyebilirsiniz.';
        header("Location: /admin/product_edit.php?id={$id}&error=1");
        exit;
    }

    $maxBoyut = 5 * 1024 * 1024; // 5 MB
    if ($_FILES['images']['size'] > $maxBoyut) {
        $_SESSION['form_error'] = 'Görsel boyutu 5 MB\'ı aşamaz.';
        header("Location: /admin/product_edit.php?id={$id}&error=1");
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uzanti   = pathinfo($_FILES['images']['name'], PATHINFO_EXTENSION);
    $dosyaAdi = uniqid('urun_', true) . '.' . strtolower($uzanti);
    $hedefYol = $uploadDir . $dosyaAdi;

    if (!move_uploaded_file($_FILES['images']['tmp_name'], $hedefYol)) {
        $_SESSION['form_error'] = 'Görsel yüklenirken bir hata oluştu.';
        header("Location: /admin/product_edit.php?id={$id}&error=1");
        exit;
    }

    // Yeni görsel başarıyla yüklendi - eski görseli diskten sil
    if ($mevcutGorselDosyasi) {
        $eskiGorselYolu = $uploadDir . $mevcutGorselDosyasi;
        if (is_file($eskiGorselYolu)) {
            @unlink($eskiGorselYolu);
        }
    }

    $imagePath = $dosyaAdi;
}

// --- VERİTABANINI GÜNCELLE ---
try {
    $stmt = $db->prepare("
        UPDATE products
        SET name = :name,
            description = :description,
            category_id = :category_id,
            price = :price,
            stock = :stock,
            images = :images
        WHERE id = :id
    ");

    $stmt->execute([
        'name'        => $name,
        'description' => $description,
        'category_id' => $categoryId,
        'price'       => (float) $price,
        'stock'       => (int) $stock,
        'images'      => $imagePath ? json_encode([$imagePath]) : null,
        'id'          => $id,
    ]);

    header('Location: /admin/products.php?success=1');
    exit;

} catch (PDOException $e) {
    error_log('Ürün güncelleme hatası: ' . $e->getMessage());
    $_SESSION['form_error'] = 'Ürün güncellenirken bir veritabanı hatası oluştu.';
    header("Location: /admin/product_edit.php?id={$id}&error=1");
    exit;
}