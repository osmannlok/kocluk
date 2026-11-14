<?php
/**
 * admin/product_add.php
 * Bahar Yöresel - Yeni Ürün Ekleme Formu
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

// Kategorileri dropdown için çek
try {
    $kategoriler = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('Kategori listeleme hatası: ' . $e->getMessage());
    $kategoriler = [];
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold h3 mb-0">Yeni Ürün Ekle</h1>
    <a href="/admin/products.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Ürün Listesine Dön
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">

        <form action="/admin/product_save.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="row g-3">

                <!-- Ürün Adı -->
                <div class="col-md-8">
                    <label for="name" class="form-label">Ürün Adı <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           placeholder="Örn: Maraş Peyniri (1kg)" required maxlength="200">
                </div>

                <!-- Kategori -->
                <div class="col-md-4">
                    <label for="category_id" class="form-label">Kategori</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($kategoriler as $kategori): ?>
                            <option value="<?php echo (int) $kategori['id']; ?>">
                                <?php echo htmlspecialchars($kategori['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($kategoriler)): ?>
                        <div class="form-text text-warning">
                            Henüz kategori eklenmemiş.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Açıklama -->
                <div class="col-12">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="4"
                              placeholder="Ürün hakkında detaylı açıklama..."></textarea>
                </div>

                <!-- Fiyat -->
                <div class="col-md-4">
                    <label for="price" class="form-label">Fiyat (₺) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="price" name="price"
                               step="0.01" min="0" placeholder="0.00" required>
                        <span class="input-group-text">₺</span>
                    </div>
                </div>

                <!-- Stok -->
                <div class="col-md-4">
                    <label for="stock" class="form-label">Stok Adedi</label>
                    <input type="number" class="form-control" id="stock" name="stock"
                           min="0" step="1" value="0" placeholder="0">
                </div>

                <!-- Görseller -->
                <div class="col-md-4">
                    <label for="images" class="form-label">Ürün Görselleri</label>
                    <input type="file" class="form-control" id="images" name="images[]"
                           accept="image/png, image/jpeg, image/webp" multiple>
                    <div class="form-text">Birden fazla görsel seçebilirsiniz (JPG, PNG, WEBP).</div>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="/admin/products.php" class="btn btn-outline-secondary">İptal</a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Ürünü Kaydet
                </button>
            </div>

        </form>

    </div>
</div>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
?>