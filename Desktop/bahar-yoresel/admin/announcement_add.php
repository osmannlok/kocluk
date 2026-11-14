<?php
/**
 * admin/announcement_add.php
 * Bahar Yöresel - Yeni Duyuru Ekleme Formu
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold h3 mb-0">Yeni Duyuru Ekle</h1>
    <a href="/admin/announcements.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Duyuru Listesine Dön
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">

        <form action="/admin/announcement_save.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="row g-3">

                <!-- Başlık -->
                <div class="col-md-8">
                    <label for="title" class="form-label">Duyuru Başlığı <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                           placeholder="Örn: Yeni Ürünlerimiz Raflarda" required maxlength="200">
                </div>

                <!-- Yayın Tarihi -->
                <div class="col-md-4">
                    <label for="publish_date" class="form-label">Yayın Tarihi</label>
                    <input type="date" class="form-control" id="publish_date" name="publish_date"
                           value="<?php echo date('Y-m-d'); ?>">
                    <div class="form-text">İleri bir tarih seçerek zamanlanmış duyuru oluşturabilirsiniz.</div>
                </div>

                <!-- İçerik -->
                <div class="col-12">
                    <label for="content" class="form-label">Duyuru İçeriği</label>
                    <textarea class="form-control" id="content" name="content" rows="6"
                              placeholder="Duyuru metnini buraya yazın..."></textarea>
                </div>

                <!-- Görsel -->
                <div class="col-md-8">
                    <label for="image" class="form-label">Duyuru Görseli</label>
                    <input type="file" class="form-control" id="image" name="image"
                           accept="image/png, image/jpeg, image/webp">
                    <div class="form-text">İsteğe bağlı. JPG, PNG veya WEBP, en fazla 5 MB.</div>
                </div>

                <!-- Yayında mı -->
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="is_published"
                               name="is_published" value="1" checked>
                        <label class="form-check-label" for="is_published">Yayınla</label>
                    </div>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="/admin/announcements.php" class="btn btn-outline-secondary">İptal</a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Duyuruyu Kaydet
                </button>
            </div>

        </form>

    </div>
</div>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
?>