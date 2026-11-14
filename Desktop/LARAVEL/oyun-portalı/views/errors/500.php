<?php
$title = 'Sunucu Hatası - Eğitim Oyun Portalı';
$description = 'Bir hata oluştu.';
ob_start();
?>
<section class="error-page">
    <div class="container">
        <h1>500</h1>
        <p>Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.</p>
        <a href="/" class="btn btn-primary">Ana Sayfaya Dön</a>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';