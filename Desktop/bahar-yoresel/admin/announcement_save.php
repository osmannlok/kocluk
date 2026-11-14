<?php
/**
 * admin/announcement_save.php
 * Bahar Yöresel - Yeni Duyuru Kaydetme İşlemi
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/announcement_add.php');
    exit;
}

// --- CSRF DOĞRULAMA ---
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    $_SESSION['form_error'] = 'Geçersiz istek (CSRF doğrulaması başarısız).';
    header('Location: /admin/announcement_add.php?error=1');
    exit;
}

// --- FORM VERİLERİNİ AL ---
$title       = trim($_POST['title'] ?? '');
$content     = trim($_POST['content'] ?? '');
$publishDate = trim($_POST['publish_date'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0; // checkbox işaretli değilse POST edilmez

/**
 * Tarih string'inin YYYY-MM-DD formatında ve gerçekten var olan bir tarih olup olmadığını kontrol eder.
 */
function gecerliTarihMi(string $tarih): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $tarih);
    return $d && $d->format('Y-m-d') === $tarih;
}

// --- VALIDATION ---
$hatalar = [];

if ($title === '') {
    $hatalar[] = 'Duyuru başlığı boş bırakılamaz.';
}

// Yayın tarihi boş gelirse bugünün tarihi kullanılır
if ($publishDate === '') {
    $publishDate = date('Y-m-d');
} elseif (!gecerliTarihMi($publishDate)) {
    $hatalar[] = 'Yayın tarihi formatı geçersiz (YYYY-MM-DD olmalı).';
}

if (!empty($hatalar)) {
    $_SESSION['form_error'] = implode(' ', $hatalar);
    header('Location: /admin/announcement_add.php?error=1');
    exit;
}

// --- GÖRSEL YÜKLEME ---
$imageName = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['form_error'] = 'Görsel yüklenirken bir hata oluştu.';
        header('Location: /admin/announcement_add.php?error=1');
        exit;
    }

    // İzinli MIME tipleri ve karşılık gelen güvenli uzantılar
    $izinliTipler = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $dosyaTipi = mime_content_type($_FILES['image']['tmp_name']);

    if (!isset($izinliTipler[$dosyaTipi])) {
        $_SESSION['form_error'] = 'Sadece JPG, PNG veya WEBP formatında görsel yükleyebilirsiniz.';
        header('Location: /admin/announcement_add.php?error=1');
        exit;
    }

    $maxBoyut = 5 * 1024 * 1024; // 5 MB
    if ($_FILES['image']['size'] > $maxBoyut) {
        $_SESSION['form_error'] = 'Görsel boyutu 5 MB\'ı aşamaz.';
        header('Location: /admin/announcement_add.php?error=1');
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/announcements/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Kullanıcının dosya adına/uzantısına güvenmeden, MIME tipinden türetilen uzantıyla benzersiz ad üret
    $imageName = uniqid('duyuru_', true) . '.' . $izinliTipler[$dosyaTipi];
    $hedefYol  = $uploadDir . $imageName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $hedefYol)) {
        $_SESSION['form_error'] = 'Görsel kaydedilirken bir hata oluştu.';
        header('Location: /admin/announcement_add.php?error=1');
        exit;
    }
}

// --- VERİTABANINA KAYDET ---
try {
    $stmt = $db->prepare("
        INSERT INTO announcements (title, content, image, publish_date, is_published)
        VALUES (:title, :content, :image, :publish_date, :is_published)
    ");

    $stmt->execute([
        'title'        => $title,
        'content'      => $content !== '' ? $content : null,
        'image'        => $imageName,
        'publish_date' => $publishDate,
        'is_published' => $isPublished,
    ]);

    header('Location: /admin/announcements.php?success=1');
    exit;

} catch (PDOException $e) {
    error_log('Duyuru kaydetme hatası: ' . $e->getMessage());

    // Veritabanına yazılamadıysa yüklenen görsel diskte yetim kalmasın
    if ($imageName !== null) {
        $yetimDosya = __DIR__ . '/../uploads/announcements/' . $imageName;
        if (is_file($yetimDosya)) {
            @unlink($yetimDosya);
        }
    }

    $_SESSION['form_error'] = 'Duyuru kaydedilirken bir veritabanı hatası oluştu.';
    header('Location: /admin/announcement_add.php?error=1');
    exit;
}