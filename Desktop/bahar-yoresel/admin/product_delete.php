<?php
/**
 * admin/product_delete.php
 * Bahar Yöresel - Ürün Silme İşlemi
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

// --- ID KONTROLÜ ---
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /admin/products.php');
    exit;
}

// --- ÜRÜNÜ BUL ---
try {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $urun = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Ürün getirme hatası (silme): ' . $e->getMessage());
    $urun = false;
}

// --- ÜRÜN BULUNAMADIYSA 404 ---
if (!$urun) {
    http_response_code(404);
    require_once __DIR__ . '/../includes/admin-header.php';
    ?>
    <div class="text-center py-5">
        <i class="bi bi-exclamation-octagon display-1 text-danger"></i>
        <h2 class="fw-bold mt-3">404 - Ürün Bulunamadı</h2>
        <p class="text-secondary">Silmek istediğiniz ürün mevcut değil veya zaten silinmiş olabilir.</p>
        <a href="/admin/products.php" class="btn btn-success mt-2">
            <i class="bi bi-arrow-left me-1"></i> Ürün Listesine Dön
        </a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}

// --- GÖRSELİ DİSKTEN SİL ---
if (!empty($urun['images'])) {
    $gorselDizisi = json_decode($urun['images'], true);

    if (is_array($gorselDizisi)) {
        $uploadDir = __DIR__ . '/../uploads/products/';

        foreach ($gorselDizisi as $gorselAdi) {
            $gorselYolu = $uploadDir . $gorselAdi;
            if (is_file($gorselYolu)) {
                @unlink($gorselYolu);
            }
        }
    }
}

// --- VERİTABANINDAN SİL ---
try {
    $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute(['id' => $id]);

    header('Location: /admin/products.php?success=1');
    exit;

} catch (PDOException $e) {
    error_log('Ürün silme hatası: ' . $e->getMessage());
    $_SESSION['form_error'] = 'Ürün silinirken bir veritabanı hatası oluştu.';
    header('Location: /admin/products.php?error=1');
    exit;
}