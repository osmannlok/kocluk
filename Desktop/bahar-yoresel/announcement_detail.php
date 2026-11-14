<?php
/**
 * announcement_detail.php
 * Bahar Yöresel - Duyuru Detay Sayfası
 */

// $db'yi header.php'den ÖNCE hazırlıyoruz: duyuru bulunamazsa HTTP 404 başlığı,
// header.php HTML çıktısı üretmeden önce gönderilebilsin.
require_once __DIR__ . '/config/database.php';

// --- ID KONTROLÜ VE DUYURUYU ÇEK ---
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$duyuru = false;

if ($id > 0) {
    try {
        // Sadece yayınlanmış ve yayın tarihi gelmiş duyurular gösterilir (taslak/zamanlanmış duyurular 404 verir)
        $stmt = $db->prepare("
            SELECT id, title, content, image, publish_date
            FROM announcements
            WHERE id = :id
              AND is_published = 1
              AND (publish_date IS NULL OR publish_date <= CURDATE())
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $duyuru = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Duyuru detay hatası: ' . $e->getMessage());
        $duyuru = false;
    }
}

// --- DUYURU BULUNAMADIYSA 404 ---
if (!$duyuru) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="container py-5 text-center">
        <i class="bi bi-exclamation-octagon display-1 text-danger"></i>
        <h1 class="h3 fw-bold mt-3">404 - Duyuru Bulunamadı</h1>
        <p class="text-secondary">Aradığınız duyuru mevcut değil veya yayından kaldırılmış olabilir.</p>
        <a href="/announcements.php" class="btn btn-success mt-2">
            <i class="bi bi-arrow-left me-1"></i> Tüm Duyurulara Dön
        </a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">

    <!-- BREADCRUMB -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/index.php" class="text-success text-decoration-none">Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="/announcements.php" class="text-success text-decoration-none">Duyurular</a></li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo htmlspecialchars($duyuru['title'], ENT_QUOTES, 'UTF-8'); ?>
            </li>
        </ol>
    </nav>

    <!-- DUYURU -->
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">

            <article class="card border-0 shadow-sm">

                <?php if (!empty($duyuru['image'])): ?>
                    <img src="/uploads/announcements/<?php echo htmlspecialchars($duyuru['image'], ENT_QUOTES, 'UTF-8'); ?>"
                         class="card-img-top"
                         alt="<?php echo htmlspecialchars($duyuru['title'], ENT_QUOTES, 'UTF-8'); ?>"
                         style="max-height: 420px; object-fit: cover;">
                <?php endif; ?>

                <div class="card-body p-4 p-md-5">

                    <?php if (!empty($duyuru['publish_date'])): ?>
                        <p class="small text-muted mb-2">
                            <i class="bi bi-calendar-event me-1"></i>
                            <?php echo date('d.m.Y', strtotime($duyuru['publish_date'])); ?>
                        </p>
                    <?php endif; ?>

                    <h1 class="fw-bold h2 mb-4">
                        <?php echo htmlspecialchars($duyuru['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </h1>

                    <?php if (!empty(trim((string) $duyuru['content']))): ?>
                        <div class="text-secondary" style="line-height: 1.8;">
                            <?php echo nl2br(htmlspecialchars($duyuru['content'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php else: ?>
                        <p class="text-secondary fst-italic mb-0">Bu duyuru için ek içerik girilmemiş.</p>
                    <?php endif; ?>

                </div>
            </article>

            <div class="mt-4">
                <a href="/announcements.php" class="btn btn-outline-success">
                    <i class="bi bi-arrow-left me-1"></i> Tüm Duyurulara Dön
                </a>
            </div>

        </div>
    </div>
    <!-- /DUYURU -->

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>