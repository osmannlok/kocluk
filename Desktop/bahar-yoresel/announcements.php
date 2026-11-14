<?php
/**
 * announcements.php
 * Bahar Yöresel - Duyurular Sayfası (sayfalamalı)
 */

require_once __DIR__ . '/includes/header.php'; // header.php içinde config/database.php de dahil edilir ($db hazır)

/**
 * Duyuru içeriğinden HTML etiketlerini temizleyip belirtilen uzunlukta kısa özet üretir.
 */
function duyuruOzeti(?string $icerik, int $uzunluk = 160): string
{
    $metin = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $icerik)));
    if (mb_strlen($metin) > $uzunluk) {
        $metin = rtrim(mb_substr($metin, 0, $uzunluk)) . '...';
    }
    return $metin;
}

/**
 * Sayfalama linki üretir.
 */
function duyuruSayfaLinki(int $sayfa): string
{
    return '?' . http_build_query(['page' => $sayfa]);
}

// --- SAYFALAMA AYARLARI ---
$perPage = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// Yayınlanmış duyuru koşulu: is_published=1 ve yayın tarihi gelmiş (tarih boşsa hemen yayında sayılır)
$yayinKosul = "
    is_published = 1
    AND (publish_date IS NULL OR publish_date <= CURDATE())
";

$duyurular = [];
$toplamDuyuru = 0;
$toplamSayfa = 1;

try {
    // Toplam yayınlanmış duyuru sayısı
    $toplamDuyuru = (int) $db->query("SELECT COUNT(*) FROM announcements WHERE $yayinKosul")->fetchColumn();

    $toplamSayfa = max(1, (int) ceil($toplamDuyuru / $perPage));
    if ($page > $toplamSayfa) {
        $page = $toplamSayfa;
    }
    $offset = ($page - 1) * $perPage;

    // Sayfadaki duyurular
    $stmt = $db->prepare("
        SELECT id, title, content, image, publish_date
        FROM announcements
        WHERE $yayinKosul
        ORDER BY publish_date DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $duyurular = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Duyuru listeleme hatası: ' . $e->getMessage());
}
?>

<div class="container py-5">

    <div class="text-center mb-5">
        <h1 class="fw-bold">Duyurular</h1>
        <p class="text-secondary mb-0">Bahar Yöresel'den en güncel haberler ve duyurular.</p>
    </div>

    <?php if (empty($duyurular)): ?>

        <div class="text-center py-5">
            <i class="bi bi-bell display-1 text-secondary"></i>
            <p class="lead text-secondary mt-3 mb-4">Henüz yayınlanmış bir duyuru bulunmuyor.</p>
            <a href="/index.php" class="btn btn-success">
                <i class="bi bi-house me-1"></i> Ana Sayfaya Dön
            </a>
        </div>

    <?php else: ?>

        <div class="row g-4">
            <?php foreach ($duyurular as $duyuru): ?>
                <?php $ozet = duyuruOzeti($duyuru['content']); ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0">

                        <?php if (!empty($duyuru['image'])): ?>
                            <img src="/uploads/announcements/<?php echo htmlspecialchars($duyuru['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                 class="card-img-top"
                                 alt="<?php echo htmlspecialchars($duyuru['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                 style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center text-secondary"
                                 style="height: 200px;">
                                <i class="bi bi-megaphone fs-1"></i>
                            </div>
                        <?php endif; ?>

                        <div class="card-body d-flex flex-column">

                            <?php if (!empty($duyuru['publish_date'])): ?>
                                <p class="small text-muted mb-2">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?php echo date('d.m.Y', strtotime($duyuru['publish_date'])); ?>
                                </p>
                            <?php endif; ?>

                            <h2 class="card-title h5 fw-bold">
                                <?php echo htmlspecialchars($duyuru['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h2>

                            <?php if ($ozet !== ''): ?>
                                <p class="card-text text-secondary mb-0">
                                    <?php echo htmlspecialchars($ozet, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- SAYFALAMA -->
        <?php if ($toplamSayfa > 1): ?>
            <nav aria-label="Sayfalama" class="mt-5">
                <ul class="pagination justify-content-center flex-wrap">

                    <li class="page-item<?php echo ($page <= 1) ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo duyuruSayfaLinki($page - 1); ?>">Önceki</a>
                    </li>

                    <?php for ($i = 1; $i <= $toplamSayfa; $i++): ?>
                        <li class="page-item<?php echo ($i === $page) ? ' active' : ''; ?>">
                            <a class="page-link" href="<?php echo duyuruSayfaLinki($i); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item<?php echo ($page >= $toplamSayfa) ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo duyuruSayfaLinki($page + 1); ?>">Sonraki</a>
                    </li>

                </ul>
            </nav>
        <?php endif; ?>
        <!-- /SAYFALAMA -->

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>