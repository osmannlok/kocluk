<?php
/**
 * products.php
 * Bahar Yöresel - Ürün Listeleme Sayfası (kategori filtreli, sayfalamalı)
 */

require_once __DIR__ . '/includes/header.php'; // header.php içinde config/database.php de dahil edilir ($db hazır)

/**
 * products.images sütunundaki JSON'dan ilk görselin dosya adını döndürür.
 */
if (!function_exists('ilkGorsel')) {
    function ilkGorsel(?string $imagesJson): ?string
    {
        if (empty($imagesJson)) {
            return null;
        }
        $dizi = json_decode($imagesJson, true);
        if (is_array($dizi) && !empty($dizi[0])) {
            return $dizi[0];
        }
        return null;
    }
}

// --- AYARLAR ---
$perPage = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$seciliKategoriId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;

// --- KATEGORİLER (sidebar için) ---
try {
    $tumKategoriler = $db->query("SELECT id, name, parent_id FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('Kategori listeleme hatası: ' . $e->getMessage());
    $tumKategoriler = [];
}

// Kategorileri üst/alt şeklinde grupla
$anaKategoriler = [];
$altKategoriler = []; // parent_id => [kategoriler]
$kategoriHaritasi = []; // id => kategori

foreach ($tumKategoriler as $kat) {
    $kategoriHaritasi[(int) $kat['id']] = $kat;
    if ($kat['parent_id'] === null) {
        $anaKategoriler[] = $kat;
    } else {
        $altKategoriler[(int) $kat['parent_id']][] = $kat;
    }
}

// --- SEÇİLİ KATEGORİ KONTROLÜ ---
$kategoriUyarisi = '';
$baslik = 'Tüm Ürünler';
$filtreKategoriIdleri = [];

if ($seciliKategoriId > 0) {
    if (isset($kategoriHaritasi[$seciliKategoriId])) {
        $baslik = $kategoriHaritasi[$seciliKategoriId]['name'];

        // Seçilen kategori + varsa alt kategorilerinin ürünlerini birlikte göster
        $filtreKategoriIdleri[] = $seciliKategoriId;
        if (!empty($altKategoriler[$seciliKategoriId])) {
            foreach ($altKategoriler[$seciliKategoriId] as $altKat) {
                $filtreKategoriIdleri[] = (int) $altKat['id'];
            }
        }
    } else {
        // Var olmayan kategori: filtre uygulanmaz, tüm ürünler gösterilir
        $kategoriUyarisi = 'Seçilen kategori bulunamadı, tüm ürünler listeleniyor.';
        $seciliKategoriId = 0;
    }
}

// --- WHERE KOŞULU ---
$whereSql = '';
$params = [];

if (!empty($filtreKategoriIdleri)) {
    $placeholders = [];
    foreach ($filtreKategoriIdleri as $i => $katId) {
        $key = ':cat' . $i;
        $placeholders[] = $key;
        $params[$key] = $katId;
    }
    $whereSql = 'WHERE category_id IN (' . implode(', ', $placeholders) . ')';
}

// --- ÜRÜNLERİ ÇEK ---
$urunler = [];
$toplamSayfa = 1;
$toplamUrun = 0;

try {
    // Toplam ürün sayısı
    $countStmt = $db->prepare("SELECT COUNT(*) FROM products $whereSql");
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val, PDO::PARAM_INT);
    }
    $countStmt->execute();
    $toplamUrun = (int) $countStmt->fetchColumn();

    $toplamSayfa = max(1, (int) ceil($toplamUrun / $perPage));
    if ($page > $toplamSayfa) {
        $page = $toplamSayfa;
    }
    $offset = ($page - 1) * $perPage;

    // Sayfadaki ürünler
    $stmt = $db->prepare("
        SELECT id, name, price, stock, images
        FROM products
        $whereSql
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $urunler = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Ürün listeleme hatası: ' . $e->getMessage());
}

/**
 * Sayfalama linki üretir (seçili kategori filtresini korur).
 */
function sayfaLinki(int $sayfa, int $kategoriId): string
{
    $query = ['page' => $sayfa];
    if ($kategoriId > 0) {
        $query = ['category_id' => $kategoriId] + $query;
    }
    return '?' . http_build_query($query);
}
?>

<div class="container py-5">
    <div class="row g-4">

        <!-- SOL SIDEBAR: KATEGORİLER -->
        <aside class="col-12 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="bi bi-grid me-1"></i> Kategoriler
                </div>

                <div class="list-group list-group-flush">
                    <a href="/products.php"
                       class="list-group-item list-group-item-action<?php echo ($seciliKategoriId === 0) ? ' active' : ''; ?>">
                        Tüm Ürünler
                    </a>

                    <?php foreach ($anaKategoriler as $anaKat): ?>
                        <a href="?category_id=<?php echo (int) $anaKat['id']; ?>"
                           class="list-group-item list-group-item-action fw-semibold<?php echo ($seciliKategoriId === (int) $anaKat['id']) ? ' active' : ''; ?>">
                            <?php echo htmlspecialchars($anaKat['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>

                        <?php if (!empty($altKategoriler[(int) $anaKat['id']])): ?>
                            <?php foreach ($altKategoriler[(int) $anaKat['id']] as $altKat): ?>
                                <a href="?category_id=<?php echo (int) $altKat['id']; ?>"
                                   class="list-group-item list-group-item-action ps-4 small<?php echo ($seciliKategoriId === (int) $altKat['id']) ? ' active' : ''; ?>">
                                    <i class="bi bi-arrow-return-right me-1"></i>
                                    <?php echo htmlspecialchars($altKat['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if (empty($tumKategoriler)): ?>
                        <div class="list-group-item text-secondary small">Henüz kategori eklenmemiş.</div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
        <!-- /SIDEBAR -->

        <!-- ANA İÇERİK: ÜRÜNLER -->
        <section class="col-12 col-lg-9">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 fw-bold mb-0"><?php echo htmlspecialchars($baslik, ENT_QUOTES, 'UTF-8'); ?></h1>
                <span class="text-secondary small"><?php echo $toplamUrun; ?> ürün</span>
            </div>

            <?php if ($kategoriUyarisi !== ''): ?>
                <div class="alert alert-warning py-2">
                    <?php echo htmlspecialchars($kategoriUyarisi, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($urunler)): ?>

                <div class="alert alert-info">
                    <?php echo ($seciliKategoriId > 0)
                        ? 'Bu kategoride henüz ürün bulunmuyor.'
                        : 'Henüz ürün eklenmemiş.'; ?>
                </div>

            <?php else: ?>

                <div class="row g-4">
                    <?php foreach ($urunler as $urun): ?>
                        <?php $gorsel = ilkGorsel($urun['images']); ?>
                        <div class="col-12 col-sm-6 col-xl-4">
                            <div class="card h-100 shadow-sm border-0">

                                <?php if ($gorsel): ?>
                                    <img src="/uploads/products/<?php echo htmlspecialchars($gorsel, ENT_QUOTES, 'UTF-8'); ?>"
                                         class="card-img-top"
                                         alt="<?php echo htmlspecialchars($urun['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                         style="height: 200px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center text-secondary"
                                         style="height: 200px;">
                                        <i class="bi bi-image fs-1"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="card-body d-flex flex-column text-center">
                                    <h5 class="card-title fw-bold">
                                        <?php echo htmlspecialchars($urun['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h5>
                                    <p class="card-text fs-5 text-success fw-semibold mb-2">
                                        <?php echo number_format((float) $urun['price'], 2, ',', '.'); ?> ₺
                                    </p>

                                    <?php if ((int) $urun['stock'] <= 0): ?>
                                        <p class="mb-3"><span class="badge bg-danger">Tükendi</span></p>
                                    <?php else: ?>
                                        <div class="mb-3"></div>
                                    <?php endif; ?>

                                    <a href="/product_detail.php?id=<?php echo (int) $urun['id']; ?>"
                                       class="btn btn-outline-success mt-auto">
                                        Detay
                                    </a>
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
                                <a class="page-link" href="<?php echo sayfaLinki($page - 1, $seciliKategoriId); ?>">Önceki</a>
                            </li>

                            <?php for ($i = 1; $i <= $toplamSayfa; $i++): ?>
                                <li class="page-item<?php echo ($i === $page) ? ' active' : ''; ?>">
                                    <a class="page-link" href="<?php echo sayfaLinki($i, $seciliKategoriId); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item<?php echo ($page >= $toplamSayfa) ? ' disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo sayfaLinki($page + 1, $seciliKategoriId); ?>">Sonraki</a>
                            </li>

                        </ul>
                    </nav>
                <?php endif; ?>
                <!-- /SAYFALAMA -->

            <?php endif; ?>

        </section>
        <!-- /ANA İÇERİK -->

    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>