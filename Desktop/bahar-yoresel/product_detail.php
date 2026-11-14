<?php
/**
 * product_detail.php
 * Bahar Yöresel - Ürün Detay Sayfası
 */

// $db'yi header.php'den ÖNCE hazırlıyoruz: ürün bulunamazsa HTTP 404 başlığı,
// header.php HTML çıktısı üretmeden önce gönderilebilsin.
require_once __DIR__ . '/config/database.php';

// WhatsApp sipariş numarası (ülke kodu ile, başında + veya 0 olmadan)
define('WHATSAPP_NUMBER', '905467446722'); // TODO: Gerçek işletme numarasıyla değiştir

if (!function_exists('ilkGorsel')) {
    function ilkGorsel(?string $imagesJson): ?string
    {
        if (empty($imagesJson)) {
            return null;
        }
        $dizi = json_decode($imagesJson, true);
        return (is_array($dizi) && !empty($dizi[0])) ? $dizi[0] : null;
    }
}

/**
 * products.images JSON'undaki tüm görsel dosya adlarını dizi olarak döndürür.
 */
function tumGorseller(?string $imagesJson): array
{
    if (empty($imagesJson)) {
        return [];
    }
    $dizi = json_decode($imagesJson, true);
    if (!is_array($dizi)) {
        return [];
    }
    return array_values(array_filter($dizi, 'is_string'));
}

// --- ID KONTROLÜ VE ÜRÜNÜ ÇEK ---
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$urun = false;

if ($id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.description, p.category_id, p.price, p.stock, p.images,
                   c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $urun = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Ürün detay hatası: ' . $e->getMessage());
        $urun = false;
    }
}

// --- ÜRÜN BULUNAMADIYSA 404 ---
if (!$urun) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="container py-5 text-center">
        <i class="bi bi-exclamation-octagon display-1 text-danger"></i>
        <h1 class="h3 fw-bold mt-3">404 - Ürün Bulunamadı</h1>
        <p class="text-secondary">Aradığınız ürün mevcut değil veya kaldırılmış olabilir.</p>
        <a href="/products.php" class="btn btn-success mt-2">
            <i class="bi bi-arrow-left me-1"></i> Tüm Ürünlere Dön
        </a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// --- GÖRSELLER ---
$gorseller  = tumGorseller($urun['images']);
$anaGorsel  = $gorseller[0] ?? null;
$stok       = (int) $urun['stock'];
$fiyatMetni = number_format((float) $urun['price'], 2, ',', '.');

// --- WHATSAPP SİPARİŞ LİNKİ ---
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$urunUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/product_detail.php?id=' . (int) $urun['id'];

$mesaj = "Merhaba, Bahar Yöresel sitesindeki şu ürünü sipariş vermek istiyorum:\n\n"
       . "Ürün: " . $urun['name'] . "\n"
       . "Fiyat: " . $fiyatMetni . " ₺\n"
       . "Link: " . $urunUrl;

$whatsappLink = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($mesaj);

// --- İLGİLİ ÜRÜNLER: aynı kategoriden rastgele 4 ürün ---
$ilgiliUrunler = [];
if (!empty($urun['category_id'])) {
    try {
        $stmt = $db->prepare("
            SELECT id, name, price, stock, images
            FROM products
            WHERE category_id = :category_id AND id <> :id
            ORDER BY RAND()
            LIMIT 4
        ");
        $stmt->execute([
            'category_id' => (int) $urun['category_id'],
            'id'          => (int) $urun['id'],
        ]);
        $ilgiliUrunler = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('İlgili ürünler hatası: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">

    <!-- BREADCRUMB -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/index.php" class="text-success text-decoration-none">Ana Sayfa</a></li>
            <li class="breadcrumb-item"><a href="/products.php" class="text-success text-decoration-none">Ürünler</a></li>
            <?php if (!empty($urun['category_name'])): ?>
                <li class="breadcrumb-item">
                    <a href="/products.php?category_id=<?php echo (int) $urun['category_id']; ?>" class="text-success text-decoration-none">
                        <?php echo htmlspecialchars($urun['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo htmlspecialchars($urun['name'], ENT_QUOTES, 'UTF-8'); ?>
            </li>
        </ol>
    </nav>

    <!-- ÜRÜN DETAYI -->
    <div class="row g-5">

        <!-- Görsel alanı -->
        <div class="col-12 col-md-6">
            <?php if ($anaGorsel): ?>
                <img id="anaGorsel"
                     src="/uploads/products/<?php echo htmlspecialchars($anaGorsel, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($urun['name'], ENT_QUOTES, 'UTF-8'); ?>"
                     class="img-fluid rounded-3 shadow-sm w-100"
                     style="max-height: 450px; object-fit: cover;">

                <?php if (count($gorseller) > 1): ?>
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <?php foreach ($gorseller as $gorsel): ?>
                            <?php $gorselYolu = '/uploads/products/' . htmlspecialchars($gorsel, ENT_QUOTES, 'UTF-8'); ?>
                            <img src="<?php echo $gorselYolu; ?>"
                                 data-src="<?php echo $gorselYolu; ?>"
                                 alt="Ürün görseli"
                                 class="img-thumbnail"
                                 style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;"
                                 onclick="document.getElementById('anaGorsel').src = this.dataset.src;">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-secondary"
                     style="height: 400px;">
                    <i class="bi bi-image display-1"></i>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bilgi alanı -->
        <div class="col-12 col-md-6">

            <?php if (!empty($urun['category_name'])): ?>
                <span class="badge bg-light text-success border border-success mb-2">
                    <?php echo htmlspecialchars($urun['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
            <?php endif; ?>

            <h1 class="fw-bold h2">
                <?php echo htmlspecialchars($urun['name'], ENT_QUOTES, 'UTF-8'); ?>
            </h1>

            <p class="display-6 text-success fw-semibold my-3"><?php echo $fiyatMetni; ?> ₺</p>

            <!-- Stok durumu -->
            <p class="mb-4">
                <?php if ($stok <= 0): ?>
                    <span class="badge bg-danger fs-6"><i class="bi bi-x-circle me-1"></i> Tükendi</span>
                <?php elseif ($stok <= 10): ?>
                    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-exclamation-circle me-1"></i> Son <?php echo $stok; ?> adet</span>
                <?php else: ?>
                    <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i> Stokta var</span>
                <?php endif; ?>
            </p>

            <!-- Açıklama -->
            <?php if (!empty(trim((string) $urun['description']))): ?>
                <h2 class="h6 fw-bold text-uppercase text-secondary">Ürün Açıklaması</h2>
                <p class="text-secondary">
                    <?php echo nl2br(htmlspecialchars($urun['description'], ENT_QUOTES, 'UTF-8')); ?>
                </p>
            <?php endif; ?>

            <!-- WhatsApp sipariş -->
            <div class="d-grid gap-2 mt-4">
                <?php if ($stok > 0): ?>
                    <a href="<?php echo htmlspecialchars($whatsappLink, ENT_QUOTES, 'UTF-8'); ?>"
                       target="_blank" rel="noopener noreferrer"
                       class="btn btn-success btn-lg">
                        <i class="bi bi-whatsapp me-2"></i> WhatsApp ile Sipariş Ver
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary btn-lg" disabled>
                        <i class="bi bi-whatsapp me-2"></i> Ürün Şu An Stokta Yok
                    </button>
                <?php endif; ?>
                <a href="/products.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Ürünlere Dön
                </a>
            </div>

        </div>
    </div>
    <!-- /ÜRÜN DETAYI -->

    <!-- İLGİLİ ÜRÜNLER -->
    <?php if (!empty($ilgiliUrunler)): ?>
        <hr class="my-5">

        <h2 class="fw-bold h4 mb-4">İlgili Ürünler</h2>

        <div class="row g-4">
            <?php foreach ($ilgiliUrunler as $ilgili): ?>
                <?php $ilgiliGorsel = ilkGorsel($ilgili['images']); ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card h-100 shadow-sm border-0">

                        <?php if ($ilgiliGorsel): ?>
                            <img src="/uploads/products/<?php echo htmlspecialchars($ilgiliGorsel, ENT_QUOTES, 'UTF-8'); ?>"
                                 class="card-img-top"
                                 alt="<?php echo htmlspecialchars($ilgili['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 style="height: 180px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center text-secondary"
                                 style="height: 180px;">
                                <i class="bi bi-image fs-1"></i>
                            </div>
                        <?php endif; ?>

                        <div class="card-body d-flex flex-column text-center">
                            <h5 class="card-title fw-bold h6">
                                <?php echo htmlspecialchars($ilgili['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            <p class="card-text fs-5 text-success fw-semibold mb-3">
                                <?php echo number_format((float) $ilgili['price'], 2, ',', '.'); ?> ₺
                            </p>
                            <a href="/product_detail.php?id=<?php echo (int) $ilgili['id']; ?>"
                               class="btn btn-outline-success mt-auto">
                                Detay
                            </a>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <!-- /İLGİLİ ÜRÜNLER -->

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>