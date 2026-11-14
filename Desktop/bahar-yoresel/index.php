<?php
/**
 * index.php
 * Bahar Yöresel - Ana Sayfa
 */

require_once __DIR__ . '/includes/header.php'; // header.php içinde config/database.php de dahil edilir ($db hazır)

// --- ÖNE ÇIKAN ÜRÜNLER: rastgele 4 ürün ---
try {
    $stmt = $db->query("
        SELECT id, name, price, images
        FROM products
        ORDER BY RAND()
        LIMIT 4
    ");
    $oneCikanUrunler = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Öne çıkan ürünler hatası: ' . $e->getMessage());
    $oneCikanUrunler = [];
}

// --- AKTİF KAMPANYALAR: en fazla 3 adet ---
try {
    $stmt = $db->query("
        SELECT id, title, description, discount_type, discount_value, end_date
        FROM campaigns
        WHERE is_active = 1
          AND (start_date IS NULL OR start_date <= CURDATE())
          AND (end_date IS NULL OR end_date >= CURDATE())
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $kampanyalar = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Kampanyalar hatası: ' . $e->getMessage());
    $kampanyalar = [];
}

/**
 * products.images sütunundaki JSON'dan ilk görselin dosya adını döndürür.
 * Görsel yoksa null döner.
 */
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

/**
 * Kampanyanın indirim türüne göre kullanıcıya gösterilecek kısa metni üretir.
 * Örn: "%15 İndirim", "50 ₺ İndirim", "Ücretsiz Kargo"
 */
function kampanyaIndirimMetni(string $tur, $deger): string
{
    switch ($tur) {
        case 'percentage':
            // 15.00 -> 15, 12.50 -> 12,5
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
?>

<!-- HERO / CAROUSEL -->
<div id="anaCarousel" class="carousel slide" data-bs-ride="carousel">

    <div class="carousel-indicators">
        <button type="button" data-bs-target="#anaCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slayt 1"></button>
        <button type="button" data-bs-target="#anaCarousel" data-bs-slide-to="1" aria-label="Slayt 2"></button>
        <button type="button" data-bs-target="#anaCarousel" data-bs-slide-to="2" aria-label="Slayt 3"></button>
    </div>

    <div class="carousel-inner">
        <div class="carousel-item active">
            <img src="/assets/img/1.jpeg" class="d-block w-100" alt="Kahramanmaraş Yöresel Ürünler" style="height: 450px; object-fit: cover;">
            <div class="carousel-caption d-none d-md-block">
                <h2 class="fw-bold">Doğal ve Yöresel Lezzetler</h2>
                <p>Kahramanmaraş'ın en özel ürünleri kapınıza geliyor.</p>
            </div>
        </div>
        <div class="carousel-item">
            <img src="/assets/img/2.jpeg" class="d-block w-100" alt="Taze Ürünler" style="height: 450px; object-fit: cover;">
            <div class="carousel-caption d-none d-md-block">
                <h2 class="fw-bold">Taptaze, Elle Yapılmış</h2>
                <p>Geleneksel üretim yöntemleriyle hazırlanan ürünler.</p>
            </div>
        </div>
        <div class="carousel-item">
            <img src="/assets/img/3.jpeg" class="d-block w-100" alt="Kampanyalar" style="height: 450px; object-fit: cover;">
            <div class="carousel-caption d-none d-md-block">
                <h2 class="fw-bold">Kampanyaları Kaçırmayın</h2>
                <p>Sınırlı süreli fırsatlarla sizi bekliyor.</p>
            </div>
        </div>
    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#anaCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Önceki</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#anaCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Sonraki</span>
    </button>
</div>
<!-- /HERO / CAROUSEL -->

<!-- HOŞ GELDİNİZ -->
<section class="py-5 bg-light text-center">
    <div class="container">
        <h1 class="fw-bold mb-3">Bahar Yöresel'e Hoş Geldiniz</h1>
        <p class="lead text-secondary mx-auto" style="max-width: 700px;">
            Kahramanmaraş'ın eşsiz yöresel ürünlerini sizlerle buluşturuyoruz.
            Doğallık, kalite ve geleneksel tat bir arada.
        </p>
    </div>
</section>
<!-- /HOŞ GELDİNİZ -->

<!-- ÖNE ÇIKAN ÜRÜNLER -->
<section class="py-5">
    <div class="container">
        <h2 class="fw-bold text-center mb-4">Öne Çıkan Ürünler</h2>

        <?php if (empty($oneCikanUrunler)): ?>

            <div class="text-center text-secondary py-4">
                <p class="mb-0">Henüz ürün eklenmemiş.</p>
            </div>

        <?php else: ?>

            <div class="row g-4">
                <?php foreach ($oneCikanUrunler as $urun): ?>
                    <?php $gorsel = ilkGorsel($urun['images']); ?>
                    <div class="col-12 col-sm-6 col-lg-3">
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
                                <p class="card-text fs-5 text-success fw-semibold mb-3">
                                    <?php echo number_format((float) $urun['price'], 2, ',', '.'); ?> ₺
                                </p>
                                <a href="/product_detail.php?id=<?php echo (int) $urun['id']; ?>"
                                   class="btn btn-outline-success mt-auto">
                                    Detay
                                </a>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
</section>
<!-- /ÖNE ÇIKAN ÜRÜNLER -->

<!-- KAMPANYALAR -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="fw-bold text-center mb-4">Kampanyalar</h2>

        <?php if (empty($kampanyalar)): ?>

            <div class="text-center text-secondary py-4">
                <p class="mb-0">Şu anda aktif bir kampanya bulunmuyor.</p>
            </div>

        <?php else: ?>

            <div class="row g-4">
                <?php foreach ($kampanyalar as $kampanya): ?>
                    <?php
                        $indirimMetni = kampanyaIndirimMetni($kampanya['discount_type'], $kampanya['discount_value']);
                        $aciklama = trim((string) $kampanya['description']);
                        if (mb_strlen($aciklama) > 120) {
                            $aciklama = mb_substr($aciklama, 0, 120) . '...';
                        }
                    ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0 border-top border-success border-4">
                            <div class="card-body d-flex flex-column">

                                <?php if ($indirimMetni !== ''): ?>
                                    <span class="badge bg-success align-self-start mb-3 fs-6">
                                        <i class="bi bi-tag-fill me-1"></i>
                                        <?php echo htmlspecialchars($indirimMetni, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>

                                <h5 class="card-title fw-bold">
                                    <?php echo htmlspecialchars($kampanya['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </h5>

                                <?php if ($aciklama !== ''): ?>
                                    <p class="card-text text-secondary">
                                        <?php echo htmlspecialchars($aciklama, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($kampanya['end_date'])): ?>
                                    <p class="card-text small text-muted mt-auto mb-0">
                                        <i class="bi bi-clock me-1"></i>
                                        Son gün: <?php echo date('d.m.Y', strtotime($kampanya['end_date'])); ?>
                                    </p>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="/campaigns.php" class="btn btn-success">
                Tüm Kampanyalar <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>
<!-- /KAMPANYALAR -->

<!-- DUYURULAR -->
<section class="py-5">
    <div class="container">
        <h2 class="fw-bold text-center mb-4">Duyurular</h2>
        <div class="row">
            <?php
            // TODO: Veritabanından son duyurular çekilecek
            // Örnek: SELECT * FROM announcements WHERE is_published = 1 AND publish_date <= CURDATE() ORDER BY publish_date DESC LIMIT 3
            ?>
            <div class="col-12 text-center text-secondary py-4">
                <p>Duyurular yakında burada listelenecek.</p>
            </div>
        </div>
    </div>
</section>
<!-- /DUYURULAR -->

<?php
require_once __DIR__ . '/includes/footer.php';
?>