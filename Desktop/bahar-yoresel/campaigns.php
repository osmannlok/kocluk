<?php
/**
 * campaigns.php
 * Bahar Yöresel - Aktif Kampanyalar Sayfası (sayfalamalı)
 */

require_once __DIR__ . '/includes/header.php'; // header.php içinde config/database.php de dahil edilir ($db hazır)

/**
 * Kampanyanın indirim türüne göre kullanıcıya gösterilecek kısa metni üretir.
 * Örn: "%15 İndirim", "50,00 ₺ İndirim", "Ücretsiz Kargo"
 */
if (!function_exists('kampanyaIndirimMetni')) {
    function kampanyaIndirimMetni(string $tur, $deger): string
    {
        switch ($tur) {
            case 'percentage':
                $oran = rtrim(rtrim(number_format((float) $deger, 2, ',', ''), '0'), ',');
                return '%' . $oran . ' İndirim';
            case 'fixed':
                return number_format((float) $deger, 2, ',', '.') . ' ₺ İndirim';
            case 'free_shipping':
                return 'Ücretsiz Kargo';
            default:
                return '';
        }
    }
}

/**
 * Sayfalama linki üretir.
 */
function kampanyaSayfaLinki(int $sayfa): string
{
    return '?' . http_build_query(['page' => $sayfa]);
}

// --- SAYFALAMA AYARLARI ---
$perPage = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// Aktif kampanya koşulu: is_active=1 ve bugün tarih aralığında (tarihler boşsa sınırsız kabul edilir)
$aktifKosul = "
    is_active = 1
    AND (start_date IS NULL OR start_date <= CURDATE())
    AND (end_date IS NULL OR end_date >= CURDATE())
";

$kampanyalar = [];
$toplamKampanya = 0;
$toplamSayfa = 1;

try {
    // Toplam aktif kampanya sayısı
    $toplamKampanya = (int) $db->query("SELECT COUNT(*) FROM campaigns WHERE $aktifKosul")->fetchColumn();

    $toplamSayfa = max(1, (int) ceil($toplamKampanya / $perPage));
    if ($page > $toplamSayfa) {
        $page = $toplamSayfa;
    }
    $offset = ($page - 1) * $perPage;

    // Sayfadaki kampanyalar
    $stmt = $db->prepare("
        SELECT id, title, description, discount_type, discount_value, start_date, end_date
        FROM campaigns
        WHERE $aktifKosul
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $kampanyalar = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Kampanya listeleme hatası: ' . $e->getMessage());
}

$bugun = new DateTime('today');
?>

<div class="container py-5">

    <div class="text-center mb-5">
        <h1 class="fw-bold">Kampanyalar</h1>
        <p class="text-secondary mb-0">Şu anda geçerli olan fırsatları kaçırmayın.</p>
    </div>

    <?php if (empty($kampanyalar)): ?>

        <div class="text-center py-5">
            <i class="bi bi-tag display-1 text-secondary"></i>
            <p class="lead text-secondary mt-3 mb-4">Şu anda aktif bir kampanya bulunmuyor.</p>
            <a href="/products.php" class="btn btn-success">
                <i class="bi bi-bag me-1"></i> Ürünlere Göz At
            </a>
        </div>

    <?php else: ?>

        <div class="row g-4">
            <?php foreach ($kampanyalar as $kampanya): ?>
                <?php
                    $indirimMetni = kampanyaIndirimMetni($kampanya['discount_type'], $kampanya['discount_value']);

                    // Kalan gün hesabı (bitiş tarihi varsa)
                    $kalanGun = null;
                    if (!empty($kampanya['end_date'])) {
                        $bitis = new DateTime($kampanya['end_date']);
                        $kalanGun = (int) $bugun->diff($bitis)->format('%r%a');
                    }
                ?>
                <div class="col-12 col-md-6">
                    <div class="card h-100 shadow-sm border-0 border-top border-success border-4">
                        <div class="card-body d-flex flex-column">

                            <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
                                <?php if ($indirimMetni !== ''): ?>
                                    <span class="badge bg-success fs-6">
                                        <i class="bi bi-tag-fill me-1"></i>
                                        <?php echo htmlspecialchars($indirimMetni, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($kalanGun !== null && $kalanGun <= 3): ?>
                                    <span class="badge bg-danger">
                                        <?php echo ($kalanGun === 0) ? 'Son gün!' : 'Son ' . $kalanGun . ' gün'; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h2 class="card-title h5 fw-bold">
                                <?php echo htmlspecialchars($kampanya['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h2>

                            <?php if (!empty(trim((string) $kampanya['description']))): ?>
                                <p class="card-text text-secondary">
                                    <?php echo nl2br(htmlspecialchars($kampanya['description'], ENT_QUOTES, 'UTF-8')); ?>
                                </p>
                            <?php endif; ?>

                            <p class="card-text small text-muted mt-auto mb-0">
                                <i class="bi bi-calendar-event me-1"></i>
                                <?php if (!empty($kampanya['start_date']) && !empty($kampanya['end_date'])): ?>
                                    <?php echo date('d.m.Y', strtotime($kampanya['start_date'])); ?>
                                    &ndash;
                                    <?php echo date('d.m.Y', strtotime($kampanya['end_date'])); ?>
                                <?php elseif (!empty($kampanya['end_date'])): ?>
                                    Son gün: <?php echo date('d.m.Y', strtotime($kampanya['end_date'])); ?>
                                <?php elseif (!empty($kampanya['start_date'])): ?>
                                    Başlangıç: <?php echo date('d.m.Y', strtotime($kampanya['start_date'])); ?>
                                <?php else: ?>
                                    Süresiz kampanya
                                <?php endif; ?>
                            </p>

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
                        <a class="page-link" href="<?php echo kampanyaSayfaLinki($page - 1); ?>">Önceki</a>
                    </li>

                    <?php for ($i = 1; $i <= $toplamSayfa; $i++): ?>
                        <li class="page-item<?php echo ($i === $page) ? ' active' : ''; ?>">
                            <a class="page-link" href="<?php echo kampanyaSayfaLinki($i); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item<?php echo ($page >= $toplamSayfa) ? ' disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo kampanyaSayfaLinki($page + 1); ?>">Sonraki</a>
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