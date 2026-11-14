<?php
declare(strict_types=1);

// resources/views/front/home.php
// Ana sayfa. Girdi: $heroBanners, $secondaryBanners, $categories, $featured, $newest, $regions, $freeThreshold.

use App\Core\View;

$heroBanners = isset($heroBanners) && is_array($heroBanners) ? $heroBanners : [];
$secondaryBanners = isset($secondaryBanners) && is_array($secondaryBanners) ? $secondaryBanners : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$featured = isset($featured) && is_array($featured) ? $featured : [];
$newest = isset($newest) && is_array($newest) ? $newest : [];
$regions = isset($regions) && is_array($regions) ? $regions : [];
$freeThreshold = isset($freeThreshold) ? (string) $freeThreshold : '0';
$siteName = (string) (((isset($settings) && is_array($settings)) ? $settings : [])['site.name'] ?? 'Yöresel Market');

// Görsel yolu: mutlak http(s) olduğu gibi, aksi halde site köküne göre.
$imgUrl = static function (mixed $p): string {
    $p = trim((string) $p);
    if ($p === '') {
        return '';
    }
    return preg_match('#^https?://#i', $p) === 1 ? $p : url('/' . ltrim($p, '/'));
};
// Bağlantı: yalnızca site içi yol veya http(s) (javascript: vb. engellenir).
$linkUrl = static function (mixed $u): string {
    $u = trim((string) $u);
    if ($u === '' || preg_match('#^(/(?!/)|https?://)#i', $u) !== 1) {
        return '';
    }
    return str_starts_with($u, '/') ? url($u) : $u;
};

$freeShip = is_numeric($freeThreshold) && (float) $freeThreshold > 0;
$heroCount = count($heroBanners);
?>
<h1 class="visually-hidden"><?= e($siteName) ?> - yöresel lezzetler kapınızda</h1>

<?php if ($heroCount > 0): ?>
<section class="ym-hero" aria-label="Kampanyalar">
    <div id="ymHero" class="carousel slide"<?= $heroCount > 1 ? ' data-bs-ride="carousel" data-bs-interval="6000"' : '' ?> aria-roledescription="carousel" aria-label="Öne çıkan kampanyalar">
<?php if ($heroCount > 1): ?>
        <div class="carousel-indicators">
<?php foreach ($heroBanners as $i => $b): ?>
            <button type="button" data-bs-target="#ymHero" data-bs-slide-to="<?= e((string) $i) ?>"<?= $i === 0 ? ' class="active" aria-current="true"' : '' ?> aria-label="<?= e('Slayt ' . ($i + 1)) ?>"></button>
<?php endforeach; ?>
        </div>
<?php endif; ?>
        <div class="carousel-inner">
<?php foreach ($heroBanners as $i => $b):
    $desktop = $imgUrl($b['image_desktop'] ?? '');
    $mobile = $imgUrl($b['image_mobile'] ?? '');
    $link = $linkUrl($b['link_url'] ?? '');
    $btnText = trim((string) ($b['button_text'] ?? ''));
    $alt = trim((string) ($b['alt_text'] ?? ''));
    if ($desktop === '') {
        continue;
    }
    ?>
            <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
                <picture>
<?php if ($mobile !== ''): ?>
                    <source media="(max-width: 767.98px)" srcset="<?= e($mobile) ?>">
<?php endif; ?>
                    <img class="ym-hero__img" src="<?= e($desktop) ?>" alt="<?= e($alt) ?>" width="1600" height="480"<?= $i === 0 ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
                </picture>
<?php if (trim((string) ($b['title'] ?? '')) !== ''): ?>
                <div class="ym-hero__caption">
                    <div class="container">
                        <p class="ym-hero__title"><?= e((string) $b['title']) ?></p>
<?php if (trim((string) ($b['subtitle'] ?? '')) !== ''): ?>
                        <p class="ym-hero__sub"><?= e((string) $b['subtitle']) ?></p>
<?php endif; ?>
<?php if ($link !== '' && $btnText !== ''): ?>
                        <a class="btn btn-accent" href="<?= e($link) ?>"><?= e($btnText) ?> <svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-arrow-right"></use></svg></a>
<?php endif; ?>
                    </div>
                </div>
<?php endif; ?>
            </div>
<?php endforeach; ?>
        </div>
<?php if ($heroCount > 1): ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#ymHero" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Önceki</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#ymHero" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Sonraki</span>
        </button>
<?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="ym-trust" aria-label="Avantajlar">
    <div class="container">
        <div class="row row-cols-2 row-cols-lg-4 g-0">
            <div class="col"><div class="ym-trust__item"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-truck"></use></svg><span><span class="ym-trust__title"><?= $freeShip ? 'Ücretsiz kargo' : 'Hızlı teslimat' ?></span><span class="ym-trust__text"><?= $freeShip ? e(money($freeThreshold)) . ' üzeri siparişlerde' : 'Özenli paketleme ile' ?></span></span></div></div>
            <div class="col"><div class="ym-trust__item"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-lock"></use></svg><span><span class="ym-trust__title">Güvenli ödeme</span><span class="ym-trust__text">Kart bilgileriniz saklanmaz</span></span></div></div>
            <div class="col"><div class="ym-trust__item"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-leaf"></use></svg><span><span class="ym-trust__title">Yöresel lezzetler</span><span class="ym-trust__text">Yerel üreticilerden</span></span></div></div>
            <div class="col"><div class="ym-trust__item"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-package"></use></svg><span><span class="ym-trust__title">Sipariş takibi</span><span class="ym-trust__text">Kargo bilgisi hesabınızda</span></span></div></div>
        </div>
    </div>
</section>

<?php if ($categories !== []): ?>
<section class="ym-section" aria-labelledby="ymCatTitle">
    <div class="container">
        <div class="ym-section__head">
            <h2 id="ymCatTitle" class="ym-section__title">Kategoriler</h2>
            <a class="ym-section__more" href="<?= e(url('/urunler')) ?>">Tüm ürünler</a>
        </div>
        <div class="ym-scroller">
<?php foreach ($categories as $cat):
    $catImg = $imgUrl($cat['image_path'] ?? '');
    $catName = (string) ($cat['name'] ?? '');
    ?>
            <a class="ym-cat" href="<?= e(url('/kategori/' . (string) ($cat['slug'] ?? ''))) ?>">
<?php if ($catImg !== ''): ?>
                <img class="ym-cat__img" src="<?= e($catImg) ?>" alt="" width="200" height="200" loading="lazy" decoding="async">
<?php else: ?>
                <span class="ym-cat__img" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($catName, 0, 1))) ?></span>
<?php endif; ?>
                <span class="ym-cat__name"><?= e($catName) ?></span>
            </a>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($featured !== []): ?>
<section class="ym-section" aria-labelledby="ymFeaturedTitle">
    <div class="container">
        <div class="ym-section__head">
            <h2 id="ymFeaturedTitle" class="ym-section__title">Öne çıkan ürünler</h2>
            <a class="ym-section__more" href="<?= e(url('/urunler')) ?>">Tümünü gör</a>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
<?php foreach ($featured as $p): ?>
            <div class="col"><?= View::partial('partials.product-card', ['product' => $p]) ?></div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($regions !== []): ?>
<section class="ym-section" aria-labelledby="ymRegionTitle">
    <div class="container">
        <div class="ym-section__head">
            <h2 id="ymRegionTitle" class="ym-section__title">Yöreden yöreye</h2>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
<?php foreach ($regions as $r): ?>
            <div class="col">
                <a class="ym-region" href="<?= e(url('/yore/' . (string) ($r['slug'] ?? ''))) ?>">
                    <svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-pin"></use></svg>
                    <span><?= e((string) ($r['name'] ?? '')) ?></span>
                </a>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($secondaryBanners !== []): ?>
<section class="ym-section" aria-label="Kampanyalar">
    <div class="container">
        <div class="row g-3">
<?php foreach ($secondaryBanners as $b):
    $img = $imgUrl($b['image_desktop'] ?? '');
    $imgMobile = $imgUrl($b['image_mobile'] ?? '');
    $link = $linkUrl($b['link_url'] ?? '');
    if ($img === '') {
        continue;
    }
    ?>
            <div class="col-12 col-md-6">
<?php if ($link !== ''): ?>
                <a class="ym-promo" href="<?= e($link) ?>">
<?php else: ?>
                <div class="ym-promo">
<?php endif; ?>
                    <picture>
<?php if ($imgMobile !== ''): ?>
                        <source media="(max-width: 767.98px)" srcset="<?= e($imgMobile) ?>">
<?php endif; ?>
                        <img class="ym-promo__img" src="<?= e($img) ?>" alt="<?= e((string) ($b['alt_text'] ?? '')) ?>" width="800" height="240" loading="lazy" decoding="async">
                    </picture>
<?php if (trim((string) ($b['title'] ?? '')) !== ''): ?>
                    <span class="ym-promo__caption">
                        <span class="ym-promo__title d-block"><?= e((string) $b['title']) ?></span>
<?php if (trim((string) ($b['subtitle'] ?? '')) !== ''): ?>
                        <span class="ym-promo__sub d-block"><?= e((string) $b['subtitle']) ?></span>
<?php endif; ?>
                    </span>
<?php endif; ?>
<?php if ($link !== ''): ?>
                </a>
<?php else: ?>
                </div>
<?php endif; ?>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="ym-section" aria-labelledby="ymWhyTitle">
    <div class="container">
        <div class="ym-section__head">
            <h2 id="ymWhyTitle" class="ym-section__title">Neden <?= e($siteName) ?>?</h2>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3">
            <div class="col"><div class="ym-why"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-leaf"></use></svg><h3>Yerel üreticiden</h3><p>Ürünlerimizi yöresinde üreten emektarlardan temin ediyoruz.</p></div></div>
            <div class="col"><div class="ym-why"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-package"></use></svg><h3>Özenli paketleme</h3><p>Gıda ürünleri tazeliği korunacak şekilde hazırlanır ve gönderilir.</p></div></div>
            <div class="col"><div class="ym-why"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-shield"></use></svg><h3>Şeffaf ürün bilgisi</h3><p>Menşei, içindekiler ve alerjen bilgileri her ürün sayfasında yer alır.</p></div></div>
            <div class="col"><div class="ym-why"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-lock"></use></svg><h3>Güvenli alışveriş</h3><p>Ödemeler güvenli altyapıyla alınır; kart bilgileriniz sitemizde saklanmaz.</p></div></div>
        </div>
    </div>
</section>

<?php if ($newest !== []): ?>
<section class="ym-section" aria-labelledby="ymNewTitle">
    <div class="container">
        <div class="ym-section__head">
            <h2 id="ymNewTitle" class="ym-section__title">Yeni gelenler</h2>
            <a class="ym-section__more" href="<?= e(url('/urunler')) ?>">Tümünü gör</a>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
<?php foreach ($newest as $p): ?>
            <div class="col"><?= View::partial('partials.product-card', ['product' => $p]) ?></div>
<?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
