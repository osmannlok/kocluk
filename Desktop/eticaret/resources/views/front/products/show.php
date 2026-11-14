<?php // resources/views/front/products/show.php

use App\Core\View;
use App\Helpers\Money;

/** @var array $product */
$imgUrl = static function (mixed $path): string {
    if (!is_string($path) || $path === '') {
        return '';
    }
    if ($path[0] === '/' && !str_starts_with($path, '//')) {
        return url($path);
    }

    return preg_match('#^https?://#i', $path) === 1 ? $path : '';
};
$percent = static function (string $price, string $final): int {
    $p = Money::toCents($price);
    $f = Money::toCents($final);

    return $p > 0 && $f < $p ? intdiv((($p - $f) * 100) + intdiv($p, 2), $p) : 0;
};
$stockInfo = static function (array $v): array {
    if (!$v['in_stock']) {
        return ['Tükendi', 'text-danger'];
    }
    if ($v['low']) {
        return ['Stok azalıyor, acele edin', 'text-warning-emphasis'];
    }

    return ['Stokta var', 'text-success'];
};

$current = $variants[0];
foreach ($variants as $v) {
    if ((int) $v['id'] === (int) $defaultVariantId) {
        $current = $v;
    }
}
[$stockText, $stockClass] = $stockInfo($current);
$pct = $current['on_sale'] ? $percent($current['price'], $current['final_price']) : 0;
$variantsJson = json_encode($variants, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$name = (string) $product['name'];
$regionName = trim((string) ($product['region_name'] ?? ''));
$originText = trim((string) ($product['origin_text'] ?? ''));
$producer = trim((string) ($product['producer_name'] ?? ''));
$fallback = 'Bu ürün için bilgi girilmemiştir.';
$descText = trim((string) ($product['description'] ?? ''));
if ($descText === '') {
    $descText = trim((string) ($product['short_description'] ?? ''));
}
$icon = static function (string $id, int $size = 18): string {
    return '<svg width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"><use href="#' . $id . '"></use></svg>';
};
?>
<div class="container py-3 py-lg-4" data-product-page data-variants="<?= e($variantsJson) ?>" data-default-variant="<?= (int) $current['id'] ?>">
    <?= View::partial('partials.breadcrumb', ['crumbs' => $crumbs]) ?>

    <article itemscope itemtype="https://schema.org/Product">
        <div class="row g-4">
            <div class="col-lg-6">
                <div data-gallery>
                    <div class="ratio ratio-1x1 bg-light rounded overflow-hidden">
                        <?php if ($images !== []): ?>
                            <?php $firstImg = $images[0]; ?>
                            <img src="<?= e($imgUrl($firstImg['path'] ?? '')) ?>" alt="<?= e((string) (($firstImg['alt_text'] ?? '') !== '' ? $firstImg['alt_text'] : $name)) ?>" class="object-fit-cover" fetchpriority="high" data-gallery-main>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center text-muted">
                                <svg width="64" height="64" aria-hidden="true" focusable="false"><use href="#i-leaf"></use></svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="d-flex gap-2 mt-2 overflow-auto pb-1" role="group" aria-label="Ürün görselleri" data-gallery-thumbs>
                            <?php foreach ($images as $i => $image): ?>
                                <?php $src = $imgUrl($image['path'] ?? ''); ?>
                                <button type="button" class="btn p-0 border rounded flex-shrink-0 overflow-hidden" data-gallery-thumb data-index="<?= (int) $i ?>" data-src="<?= e($src) ?>" data-alt="<?= e((string) (($image['alt_text'] ?? '') !== '' ? $image['alt_text'] : $name)) ?>" aria-label="Görsel <?= (int) $i + 1 ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>">
                                    <img src="<?= e($src) ?>" alt="" width="64" height="64" class="object-fit-cover d-block" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-6">
                <h1 class="h3 mb-1" itemprop="name"><?= e($name) ?></h1>
                <?php if ($regionName !== '' || $originText !== ''): ?>
                    <p class="text-muted mb-2">
                        <?= $icon('i-pin', 16) ?>
                        <?= e(trim($regionName . ($regionName !== '' && $originText !== '' ? ' · ' : '') . $originText)) ?>
                    </p>
                <?php endif; ?>
                <?php if (trim((string) ($product['short_description'] ?? '')) !== ''): ?>
                    <p class="mb-3"><?= e((string) $product['short_description']) ?></p>
                <?php endif; ?>

                <fieldset class="mb-3">
                    <legend class="fs-6 fw-semibold">Gramaj / Boyut</legend>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($variants as $v): ?>
                            <?php $isCur = (int) $v['id'] === (int) $current['id']; ?>
                            <button type="button" class="btn <?= $isCur ? 'btn-success' : 'btn-outline-success' ?><?= $v['in_stock'] ? '' : ' text-decoration-line-through' ?>" data-variant-button data-variant-id="<?= (int) $v['id'] ?>" aria-pressed="<?= $isCur ? 'true' : 'false' ?>">
                                <?= e($v['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="d-flex align-items-baseline flex-wrap gap-2 mb-2" aria-live="polite">
                    <span class="fs-3 fw-bold text-success" data-price-final><?= e(money($current['final_price'])) ?></span>
                    <del class="text-muted<?= $current['on_sale'] ? '' : ' d-none' ?>" data-price-old><?= e(money($current['price'])) ?></del>
                    <span class="badge text-bg-danger<?= $pct > 0 ? '' : ' d-none' ?>" data-price-badge>%<?= (int) $pct ?> indirim</span>
                </div>

                <p class="small mb-3 <?= e($stockClass) ?>" role="status" data-stock-text><?= e($stockText) ?></p>

                <div class="d-flex gap-2 align-items-stretch mb-3">
                    <div class="input-group flex-nowrap" data-width="140" role="group" aria-label="Adet">
                        <button type="button" class="btn btn-outline-secondary" data-qty-minus aria-label="Adedi azalt">&minus;</button>
                        <input type="number" class="form-control text-center" id="ymQty" min="1" max="99" value="1" inputmode="numeric" aria-label="Adet" data-qty-input>
                        <button type="button" class="btn btn-outline-secondary" data-qty-plus aria-label="Adedi artır">+</button>
                    </div>
                    <button type="button" class="btn btn-success flex-grow-1" data-add-to-cart data-main-add data-variant-id="<?= (int) $current['id'] ?>" data-quantity="1"<?= $current['in_stock'] ? '' : ' disabled' ?>>
                        <?= $icon('i-cart') ?> <span data-add-label><?= $current['in_stock'] ? 'Sepete ekle' : 'Tükendi' ?></span>
                    </button>
                </div>

                <p class="small text-muted mb-3">
                    SKU: <span data-sku><?= e($current['sku']) ?></span> ·
                    Ağırlık: <span data-weight><?= (int) $current['weight_grams'] ?> g</span>
                </p>

                <ul class="list-unstyled small mb-0 vstack gap-2">
                    <li><?= $icon('i-truck') ?> <?= e(money($freeThreshold)) ?> ve üzeri siparişlerde ücretsiz kargo</li>
                    <li><?= $icon('i-shield') ?> Güvenli ödeme; kart bilgileriniz sistemimizde saklanmaz</li>
                    <li><?= $icon('i-leaf') ?> Yöresel üreticilerden<?= $producer !== '' ? ': ' . e($producer) : '' ?></li>
                </ul>
            </div>
        </div>

        <div class="mt-4">
            <ul class="nav nav-tabs" id="ymProductTabs" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active" id="tab-desc-btn" data-bs-toggle="tab" data-bs-target="#tab-desc" type="button" role="tab" aria-controls="tab-desc" aria-selected="true">Açıklama</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" id="tab-ingr-btn" data-bs-toggle="tab" data-bs-target="#tab-ingr" type="button" role="tab" aria-controls="tab-ingr" aria-selected="false" tabindex="-1">İçindekiler ve alerjen</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" id="tab-store-btn" data-bs-toggle="tab" data-bs-target="#tab-store" type="button" role="tab" aria-controls="tab-store" aria-selected="false" tabindex="-1">Saklama</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" id="tab-ship-btn" data-bs-toggle="tab" data-bs-target="#tab-ship" type="button" role="tab" aria-controls="tab-ship" aria-selected="false" tabindex="-1">Teslimat ve iade</button></li>
            </ul>
            <div class="tab-content border border-top-0 rounded-bottom p-3">
                <div class="tab-pane fade show active" id="tab-desc" role="tabpanel" aria-labelledby="tab-desc-btn" tabindex="0">
                    <p class="mb-0"><?= $descText !== '' ? nl2br(e($descText)) : e($fallback) ?></p>
                </div>
                <div class="tab-pane fade" id="tab-ingr" role="tabpanel" aria-labelledby="tab-ingr-btn" tabindex="0">
                    <h2 class="h6">İçindekiler</h2>
                    <p><?= trim((string) ($product['ingredients'] ?? '')) !== '' ? nl2br(e((string) $product['ingredients'])) : e($fallback) ?></p>
                    <h2 class="h6">Alerjen bilgisi</h2>
                    <p class="mb-0"><?= trim((string) ($product['allergen_info'] ?? '')) !== '' ? nl2br(e((string) $product['allergen_info'])) : e($fallback) ?></p>
                </div>
                <div class="tab-pane fade" id="tab-store" role="tabpanel" aria-labelledby="tab-store-btn" tabindex="0">
                    <p><strong>Saklama koşulu:</strong> <?= trim((string) ($product['storage_conditions'] ?? '')) !== '' ? e((string) $product['storage_conditions']) : e($fallback) ?></p>
                    <p class="mb-0"><strong>Raf ömrü:</strong> <?= trim((string) ($product['shelf_life_info'] ?? '')) !== '' ? e((string) $product['shelf_life_info']) : e($fallback) ?></p>
                </div>
                <div class="tab-pane fade" id="tab-ship" role="tabpanel" aria-labelledby="tab-ship-btn" tabindex="0">
                    <p><?= e(money($freeThreshold)) ?> ve üzeri siparişlerde kargo ücretsizdir. Siparişiniz kargoya verildiğinde takip numarası hesabınızda ve e-postanızla paylaşılır.</p>
                    <p class="mb-0">Gıda ürünlerinde mesafeli satış mevzuatındaki cayma hakkı istisnası uygulanabilir. Ayıplı veya hasarlı ürün için <a href="<?= e(url('/iletisim')) ?>">bize ulaşın</a>.</p>
                </div>
            </div>
        </div>

        <?php /* schema.org mikroveri: varyant başına bir Offer; tam stok sayısı yazılmaz */ ?>
        <div class="d-none">
            <meta itemprop="description" content="<?= e(mb_substr($descText, 0, 300, 'UTF-8')) ?>">
            <?php if ($images !== []): ?>
                <link itemprop="image" href="<?= e($imgUrl($images[0]['path'] ?? '')) ?>">
            <?php endif; ?>
            <?php if ($producer !== ''): ?>
                <span itemprop="brand" itemscope itemtype="https://schema.org/Brand"><meta itemprop="name" content="<?= e($producer) ?>"></span>
            <?php endif; ?>
            <?php foreach ($variants as $v): ?>
                <span itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                    <meta itemprop="sku" content="<?= e($v['sku']) ?>">
                    <meta itemprop="price" content="<?= e($v['final_price']) ?>">
                    <meta itemprop="priceCurrency" content="TRY">
                    <link itemprop="url" href="<?= e(url('/urun/' . $product['slug'])) ?>">
                    <link itemprop="availability" href="<?= $v['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>">
                </span>
            <?php endforeach; ?>
        </div>
    </article>

    <?php if ($related !== []): ?>
        <section class="mt-5" aria-labelledby="ymRelatedTitle">
            <h2 class="h4 mb-3" id="ymRelatedTitle">Benzer ürünler</h2>
            <div class="row row-cols-2 row-cols-md-4 g-3">
                <?php foreach ($related as $item): ?>
                    <div class="col"><?= View::partial('partials.product-card', ['product' => $item]) ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="d-lg-none py-4" aria-hidden="true"></div>
</div>

<div class="fixed-bottom bg-body border-top shadow-sm d-lg-none p-2" data-sticky-bar>
    <div class="container d-flex align-items-center gap-3">
        <div class="me-auto text-truncate">
            <div class="small text-muted text-truncate"><?= e($name) ?></div>
            <div class="fw-bold text-success" data-price-final><?= e(money($current['final_price'])) ?></div>
        </div>
        <button type="button" class="btn btn-success" data-add-to-cart data-variant-id="<?= (int) $current['id'] ?>" data-quantity="1"<?= $current['in_stock'] ? '' : ' disabled' ?>>
            <span data-add-label><?= $current['in_stock'] ? 'Sepete ekle' : 'Tükendi' ?></span>
        </button>
    </div>
</div>