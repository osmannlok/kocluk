<?php
$title = 'Sayfa Bulunamadı - Eğitim Oyun Portalı';
$description = 'Aradığınız sayfa bulunamadı.';
ob_start();
?>
<section class="error-page">
    <div class="container">
        <h1>404</h1>
        <p>Aradığınız sayfa bulunamadı.</p>
        <a href="/" class="btn btn-primary">Ana Sayfaya Dön</a>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';