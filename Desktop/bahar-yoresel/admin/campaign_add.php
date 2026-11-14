<?php
/**
 * admin/campaign_add.php
 * Bahar Yöresel - Yeni Kampanya Ekleme Formu
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold h3 mb-0">Yeni Kampanya Ekle</h1>
    <a href="/admin/campaigns.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Kampanya Listesine Dön
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">

        <form action="/admin/campaign_save.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="row g-3">

                <!-- Başlık -->
                <div class="col-md-8">
                    <label for="title" class="form-label">Kampanya Başlığı <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                           placeholder="Örn: Sonbahar İndirimi" required maxlength="200">
                </div>

                <!-- İndirim Türü -->
                <div class="col-md-4">
                    <label for="discount_type" class="form-label">İndirim Türü <span class="text-danger">*</span></label>
                    <select class="form-select" id="discount_type" name="discount_type" required>
                        <option value="">Seçiniz</option>
                        <option value="percentage">Yüzde İndirim (%)</option>
                        <option value="fixed">Sabit Tutar (₺)</option>
                        <option value="free_shipping">Ücretsiz Kargo</option>
                    </select>
                </div>

                <!-- Açıklama -->
                <div class="col-12">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="3"
                              placeholder="Kampanya detayları..."></textarea>
                </div>

                <!-- İndirim Değeri -->
                <div class="col-md-4">
                    <label for="discount_value" class="form-label">İndirim Değeri</label>
                    <input type="number" class="form-control" id="discount_value" name="discount_value"
                           step="0.01" min="0" placeholder="Örn: 15.00">
                    <div class="form-text">Ücretsiz kargo seçiliyse boş bırakabilirsiniz.</div>
                </div>

                <!-- Başlangıç Tarihi -->
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" id="start_date" name="start_date">
                </div>

                <!-- Bitiş Tarihi -->
                <div class="col-md-4">
                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                    <input type="date" class="form-control" id="end_date" name="end_date">
                </div>

                <!-- Aktif mi -->
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">Kampanya Aktif</label>
                    </div>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="/admin/campaigns.php" class="btn btn-outline-secondary">İptal</a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Kampanyayı Kaydet
                </button>
            </div>

        </form>

    </div>
</div>

<script>
// discount_type "free_shipping" seçilirse discount_value alanını devre dışı bırak (kullanıcı deneyimi için basit bir yardımcı)
document.getElementById('discount_type').addEventListener('change', function () {
    const discountValueInput = document.getElementById('discount_value');
    if (this.value === 'free_shipping') {
        discountValueInput.value = '';
        discountValueInput.disabled = true;
    } else {
        discountValueInput.disabled = false;
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
?>