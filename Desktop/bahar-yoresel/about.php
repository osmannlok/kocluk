<?php
/**
 * about.php
 * Bahar Yöresel - Hakkımızda Sayfası (statik içerik)
 */

require_once __DIR__ . '/includes/header.php';

// Üretim süreci adımları (içerik burada düzenlenebilir)
$uretimAdimlari = [
    [
        'ikon'     => 'bi-basket2',
        'baslik'   => 'Yerel Üreticiden Tedarik',
        'aciklama' => 'Ürünlerimizi Kahramanmaraş ve çevresindeki üreticilerden, hammaddenin yetiştiği bölgeden doğrudan temin ederiz.',
    ],
    [
        'ikon'     => 'bi-search-heart',
        'baslik'   => 'Özenle Seçim',
        'aciklama' => 'Her parti, tazelik ve kalite açısından tek tek kontrol edilir; standardımızı karşılamayan ürün rafa çıkmaz.',
    ],
    [
        'ikon'     => 'bi-fire',
        'baslik'   => 'Geleneksel Yöntemle Hazırlık',
        'aciklama' => 'Nesilden nesile aktarılan tariflerle, gereksiz katkı maddesi kullanmadan hazırlanır ve işlenir.',
    ],
    [
        'ikon'     => 'bi-box-seam',
        'baslik'   => 'Hijyenik Paketleme ve Teslimat',
        'aciklama' => 'Ürünler lezzetini ve tazeliğini koruyacak şekilde paketlenir, özenle size ulaştırılır.',
    ],
];

// Değerlerimiz
$degerler = [
    [
        'ikon'     => 'bi-tree',
        'baslik'   => 'Doğallık',
        'aciklama' => 'Toprağın verdiğini olduğu gibi sofranıza taşımayı ilke edindik.',
    ],
    [
        'ikon'     => 'bi-award',
        'baslik'   => 'Kalite',
        'aciklama' => 'Her ürünümüzde aynı özeni ve tutarlı lezzeti hedefleriz.',
    ],
    [
        'ikon'     => 'bi-people',
        'baslik'   => 'Yerel Üretime Destek',
        'aciklama' => 'Bölgenin üreticileriyle çalışarak yerel ekonomiye katkı sağlarız.',
    ],
];
?>

<!-- SAYFA BAŞLIĞI / HERO -->
<section class="bg-success text-white text-center py-5">
    <div class="container py-4">
        <p class="text-uppercase small fw-semibold mb-2" style="letter-spacing: 2px; opacity: .85;">
            Hakkımızda
        </p>
        <h1 class="display-5 fw-bold mb-3">Kahramanmaraş'ın Lezzeti, Sofranıza</h1>
        <p class="lead mx-auto mb-0" style="max-width: 700px; opacity: .95;">
            Bahar Yöresel, bu toprakların geleneksel tatlarını doğallığından ödün vermeden
            sizlerle buluşturmak için yola çıktı.
        </p>
    </div>
</section>
<!-- /HERO -->

<!-- MARKA HİKÂYESİ -->
<section class="py-5">
    <div class="container py-3">
        <div class="row align-items-center g-5">

            <div class="col-12 col-lg-6 order-lg-2">
                <div class="bg-light rounded-4 overflow-hidden shadow-sm" style="min-height: 320px;">
                    <img src="/assets/img/about-story.jpg"
                         alt="Bahar Yöresel marka hikâyesi"
                         class="w-100 h-100"
                         style="min-height: 320px; max-height: 420px; object-fit: cover;"
                         onerror="this.style.display='none';">
                </div>
            </div>

            <div class="col-12 col-lg-6 order-lg-1">
                <span class="badge bg-light text-success border border-success mb-3">Hikâyemiz</span>
                <h2 class="fw-bold mb-4">Bir Sofra Geleneğinden Doğdu</h2>
                <p class="text-secondary" style="line-height: 1.85;">
                    Kahramanmaraş; bereketli toprakları, kendine has mutfağı ve
                    nesilden nesile aktarılan üretim kültürüyle bilinir. Bahar Yöresel,
                    bu zengin mirası herkesin ulaşabileceği bir yere taşımak fikriyle doğdu.
                </p>
                <p class="text-secondary" style="line-height: 1.85;">
                    Amacımız basit: bölgenin gerçek tadını, aracısız ve güvenilir bir şekilde,
                    evinizin sofrasına getirmek. Her üründe, o toprağın emeğini ve
                    üreticinin alın terini hissetmenizi isteriz.
                </p>
                <p class="text-secondary mb-0" style="line-height: 1.85;">
                    Bugün de aynı heyecanla, yerel üreticilerle el ele çalışıyor ve
                    sofralarınıza gelen her ürünün arkasında durmaya devam ediyoruz.
                </p>
            </div>

        </div>
    </div>
</section>
<!-- /MARKA HİKÂYESİ -->

<!-- YÖRESEL ÜRETİM VURGUSU -->
<section class="py-5 bg-light">
    <div class="container py-3">
        <div class="row align-items-center g-5">

            <div class="col-12 col-lg-6">
                <div class="bg-white rounded-4 overflow-hidden shadow-sm" style="min-height: 320px;">
                    <img src="/assets/img/about-local.jpg"
                         alt="Yöresel üretim"
                         class="w-100 h-100"
                         style="min-height: 320px; max-height: 420px; object-fit: cover;"
                         onerror="this.style.display='none';">
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <span class="badge bg-white text-success border border-success mb-3">Yöresel Üretim</span>
                <h2 class="fw-bold mb-4">Bu Topraklardan, Bu Ellerden</h2>
                <p class="text-secondary" style="line-height: 1.85;">
                    "Yöresel" bizim için bir etiket değil, bir taahhüt. Ürünlerimizin
                    hammaddesi bölgenin kendi toprağında yetişir; işleme ve hazırlık
                    aşamaları da bölgenin üretim geleneğine sadık kalınarak yürütülür.
                </p>
                <p class="text-secondary" style="line-height: 1.85;">
                    Yerel üreticilerle kurduğumuz doğrudan ilişki sayesinde hem ürünün
                    kaynağını biliyor hem de üreticinin emeğinin karşılığını almasına
                    katkı sağlıyoruz.
                </p>

                <ul class="list-unstyled mb-0">
                    <li class="d-flex align-items-start mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                        <span class="text-secondary">Bölge üreticilerinden doğrudan tedarik</span>
                    </li>
                    <li class="d-flex align-items-start mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                        <span class="text-secondary">Geleneksel tarif ve yöntemlere bağlılık</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                        <span class="text-secondary">Doğallığı önceleyen, sade içerikler</span>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</section>
<!-- /YÖRESEL ÜRETİM -->

<!-- DEĞERLERİMİZ -->
<section class="py-5">
    <div class="container py-3">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Değerlerimiz</h2>
            <p class="text-secondary mb-0">Her adımda bizi yönlendiren ilkeler.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($degerler as $deger): ?>
                <div class="col-12 col-md-4">
                    <div class="card h-100 border-0 shadow-sm text-center">
                        <div class="card-body p-4">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success mb-3"
                                 style="width: 72px; height: 72px;">
                                <i class="bi <?php echo htmlspecialchars($deger['ikon'], ENT_QUOTES, 'UTF-8'); ?> fs-2"></i>
                            </div>
                            <h3 class="h5 fw-bold"><?php echo htmlspecialchars($deger['baslik'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-secondary mb-0"><?php echo htmlspecialchars($deger['aciklama'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<!-- /DEĞERLERİMİZ -->

<!-- ÜRETİM SÜREÇLERİ -->
<section class="py-5 bg-light">
    <div class="container py-3">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Üretim Sürecimiz</h2>
            <p class="text-secondary mb-0">Tarladan sofranıza uzanan yolculuk.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($uretimAdimlari as $index => $adim): ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4 text-center position-relative">
                            <span class="badge bg-success rounded-pill position-absolute top-0 start-50 translate-middle px-3 py-2">
                                <?php echo $index + 1; ?>
                            </span>
                            <i class="bi <?php echo htmlspecialchars($adim['ikon'], ENT_QUOTES, 'UTF-8'); ?> display-5 text-success d-block mt-3 mb-3"></i>
                            <h3 class="h6 fw-bold"><?php echo htmlspecialchars($adim['baslik'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="small text-secondary mb-0"><?php echo htmlspecialchars($adim['aciklama'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<!-- /ÜRETİM SÜREÇLERİ -->

<!-- ÇAĞRI (CTA) -->
<section class="py-5 text-center">
    <div class="container py-3">
        <h2 class="fw-bold mb-3">Yöresel Lezzetleri Keşfedin</h2>
        <p class="text-secondary mx-auto mb-4" style="max-width: 600px;">
            Kahramanmaraş'ın özel ürünlerini incelemek ve sipariş vermek için ürün
            sayfamıza göz atabilirsiniz.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="/products.php" class="btn btn-success btn-lg">
                <i class="bi bi-bag me-1"></i> Ürünleri İncele
            </a>
            <a href="/contact.php" class="btn btn-outline-success btn-lg">
                <i class="bi bi-envelope me-1"></i> Bize Ulaşın
            </a>
        </div>
    </div>
</section>
<!-- /ÇAĞRI -->

<?php
require_once __DIR__ . '/includes/footer.php';
?>