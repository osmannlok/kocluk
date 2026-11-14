
<?php
/**
 * admin/index.php
 * Bahar Yöresel - Admin Panel Ana Sayfa (Dashboard)
 */

require_once __DIR__ . '/../includes/admin-header.php';
// Not: Giriş kontrolü (session yoksa login'e yönlendirme) admin-header.php içinde zaten yapılıyor.
?>

<!-- HOŞ GELDİNİZ -->
<div class="mb-4">
    <h1 class="fw-bold">Hoş geldiniz, Admin</h1>
    <p class="text-secondary">Bahar Yöresel yönetim paneline hoş geldiniz. Aşağıdan hızlıca işlemlere erişebilirsiniz.</p>
</div>
<!-- /HOŞ GELDİNİZ -->

<!-- HIZLI ERİŞİM KARTLARI -->
<div class="row g-4">

    <div class="col-md-4">
        <a href="#" class="text-decoration-none">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <i class="bi bi-box-seam display-4 text-primary mb-3"></i>
                    <h5 class="card-title fw-bold">Ürünler</h5>
                    <p class="card-text text-secondary small">Ürünleri ekle, düzenle veya listele.</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="#" class="text-decoration-none">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <i class="bi bi-megaphone display-4 text-success mb-3"></i>
                    <h5 class="card-title fw-bold">Kampanyalar</h5>
                    <p class="card-text text-secondary small">Aktif kampanyaları yönet.</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="#" class="text-decoration-none">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bell display-4 text-warning mb-3"></i>
                    <h5 class="card-title fw-bold">Duyurular</h5>
                    <p class="card-text text-secondary small">Site duyurularını güncelle.</p>
                </div>
            </div>
        </a>
    </div>

</div>
<!-- /HIZLI ERİŞİM KARTLARI -->

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
?>