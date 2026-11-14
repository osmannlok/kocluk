<?php
/**
 * admin/product_edit.php
 * Bahar Yöresel - Ürün Düzenleme Formu
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

// --- ID KONTROLÜ ---
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    require_once __DIR__ . '/../includes/admin-header.php';
    echo '<div class="alert alert-danger">Geçersiz ürün ID\'si.</div>';
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}

// --- ÜRÜNÜ ÇEK ---
try {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $urun = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Ürün getirme hatası: ' . $e->getMessage());
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
        <p class="text-secondary">Aradığınız ürün mevcut değil veya silinmiş olabilir.</p>
        <a href="/admin/products.php" class="btn btn-success mt-2">
            <i class="bi bi-arrow-left me-1"></i> Ürün Listesine Dön
        </a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}

// Kategorileri dropdown için çek
try {
    $kategoriler = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('Kategori listeleme hatası: ' . $e->getMessage());
    $kategoriler = [];
}

// Mevcut görseli JSON'dan çöz (ilk görseli göstermek için)
$mevcutGorsel = null;
if (!empty($urun['images'])) {
    $gorselDizisi = json_decode($urun['images'], true);
    if (is_array($gorselDizisi) && !empty($gorselDizisi)) {
        $mevcutGorsel = $gorselDizisi[0];
    }
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold h3 mb-0">Ürün Düzenle</h1>
    <a href="/admin/products.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Ürün Listesine Dön
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">

        <form action="/admin/product_update.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $urun['id']; ?>">

            <div class="row g-3">

                <!-- Ürün Adı -->
                <div class="col-md-8">
                    <label for="name" class="form-label">Ürün Adı <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?php echo htmlspecialchars($urun['name'], ENT_QUOTES, 'UTF-8'); ?>"
                           required maxlength="200">
                </div>

                <!-- Kategori -->
                <div class="col-md-4">
                    <label for="category_id" class="form-label">Kategori</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($kategoriler as $kategori): ?>
                            <option value="<?php echo (int) $kategori['id']; ?>"
                                <?php echo ((int) $urun['category_id'] === (int) $kategori['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kategori['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Açıklama -->
                <div class="col-12">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php
                        echo htmlspecialchars($urun['description'] ?? '', ENT_QUOTES, 'UTF-8');
                    ?></textarea>
                </div>

                <!-- Fiyat -->
                <div class="col-md-4">
                    <label for="price" class="form-label">Fiyat (₺) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="price" name="price"
                               step="0.01" min="0" value="<?php echo htmlspecialchars($urun['price']); ?>" required>
                        <span class="input-group-text">₺</span>
                    </div>
                </div>

                <!-- Stok -->
                <div class="col-md-4">
                    <label for="stock" class="form-label">Stok Adedi</label>
                    <input type="number" class="form-control" id="stock" name="stock"
                           min="0" step="1" value="<?php echo (int) $urun['stock']; ?>">
                </div>

                <!-- Görsel -->
                <div class="col-md-4">
                    <label for="images" class="form-label">Ürün Görseli</label>

                    <?php if ($mevcutGorsel): ?>
                        <div class="mb-2">
                            <img src="/uploads/products/<?php echo htmlspecialchars($mevcutGorsel); ?>"
                                 alt="Mevcut görsel" class="img-thumbnail" style="max-height: 100px;">
                            <div class="form-text">Mevcut görsel</div>
                        </div>
                    <?php endif; ?>

                    <input type="file" class="form-control" id="images" name="images"
                           accept="image/png, image/jpeg, image/webp">
                    <div class="form-text">Değiştirmek istemiyorsanız boş bırakabilirsiniz.</div>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="/admin/products.php" class="btn btn-outline-secondary">İptal</a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Değişiklikleri Kaydet
                </button>
            </div>

        </form>

    </div>
</div>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
?>