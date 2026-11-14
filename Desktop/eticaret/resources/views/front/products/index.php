<?php // resources/views/front/products/index.php

use App\Core\View;

/** @var array $products */
$banners = $banners ?? [];
$subcategories = $subcategories ?? [];
$category = $category ?? null;
$description = $description ?? null;

$link = static function (string $base, array $p): string {
    $qs = http_build_query($p, '', '&', PHP_QUERY_RFC3986);

    return url($base) . ($qs !== '' ? '?' . $qs : '');
};
$imgUrl = static function (mixed $path): string {
    if (!is_string($path) || $path === '') {
        return '';
    }
    if ($path[0] === '/' && !str_starts_with($path, '//')) {
        return url($path);
    }

    return preg_match('#^https?://#i', $path) === 1 ? $path : '';
};
$safeHref = static function (mixed $v): ?string {
    if (!is_string($v) || $v === '') {
        return null;
    }
    if ($v[0] === '/' && !str_starts_with($v, '//')) {
        return url($v);
    }

    return preg_match('#^https?://#i', $v) === 1 ? $v : null;
};

$sortLabels = [
    'newest' => 'En yeni',
    'price_asc' => 'Fiyat: Düşükten yükseğe',
    'price_desc' => 'Fiyat: Yüksekten düşüğe',
    'name' => 'İsme göre (A-Z)',
];

$activeRegionName = null;
foreach ($regions as $r) {
    if ((string) $r['slug'] === (string) $activeRegionSlug) {
        $activeRegionName = (string) $r['name'];
    }
}

// Aktif filtre çipleri: her çip kendi parametresini kaldıran bağlantı taşır.
$chips = [];
if (($filters['q'] ?? '') !== '') {
    $chips[] = ['label' => 'Arama: ' . $filters['q'], 'drop' => ['q']];
}
if (($filters['min_price'] ?? null) !== null) {
    $chips[] = ['label' => 'En az ' . money((string) $filters['min_price']), 'drop' => ['min_price']];
}
if (($filters['max_price'] ?? null) !== null) {
    $chips[] = ['label' => 'En çok ' . money((string) $filters['max_price']), 'drop' => ['max_price']];
}
if (!empty($filters['in_stock'])) {
    $chips[] = ['label' => 'Stokta olanlar', 'drop' => ['in_stock']];
}
if (!empty($filters['on_sale'])) {
    $chips[] = ['label' => 'İndirimli ürünler', 'drop' => ['indirimli']];
}
if (!$regionLocked && $activeRegionSlug !== null && $activeRegionName !== null) {
    $chips[] = ['label' => 'Yöre: ' . $activeRegionName, 'drop' => ['yore']];
}

$filterPartial = [
    'baseUrl' => $baseUrl,
    'filters' => $filters,
    'sort' => $sort,
    'queryParams' => $queryParams,
    'categoryTree' => $categoryTree,
    'activeCategoryId' => $activeCategoryId,
    'regions' => $regions,
    'activeRegionSlug' => $activeRegionSlug,
    'regionLocked' => $regionLocked,
];
$total = (int) $products['total'];
?>
<div class="container py-3 py-lg-4" data-catalog-list>
    <?= View::partial('partials.breadcrumb', ['crumbs' => $crumbs]) ?>

    <?php if ($banners !== [] && (int) $pager['page'] === 1): ?>
        <div class="mb-3">
            <?php foreach ($banners as $banner): ?>
                <?php
                $desktop = $imgUrl($banner['image_desktop'] ?? null);
                $mobile = $imgUrl($banner['image_mobile'] ?? null);
                $href = $safeHref($banner['link_url'] ?? null);
                if ($desktop === '') {
                    continue;
                }
                ?>
                <?php if ($href !== null): ?><a href="<?= e($href) ?>" class="d-block mb-2"><?php endif; ?>
                <picture>
                    <?php if ($mobile !== ''): ?>
                        <source media="(max-width: 767.98px)" srcset="<?= e($mobile) ?>">
                    <?php endif; ?>
                    <img src="<?= e($desktop) ?>" alt="<?= e((string) ($banner['alt_text'] ?? $banner['title'] ?? '')) ?>" class="img-fluid rounded w-100">
                </picture>
                <?php if ($href !== null): ?></a><?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <header class="mb-3">
        <h1 class="h3 mb-1"><?= e($heading) ?></h1>
        <?php if (is_string($description) && trim($description) !== ''): ?>
            <p class="text-muted mb-0"><?= nl2br(e($description)) ?></p>
        <?php endif; ?>
    </header>

    <?php if ($subcategories !== []): ?>
        <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Alt kategoriler">
            <?php foreach ($subcategories as $sub): ?>
                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= e(url('/kategori/' . $sub['slug'])) ?>"><?= e((string) $sub['name']) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <div class="row g-4">
        <aside class="col-lg-3 d-none d-lg-block" aria-label="Filtreler">
            <?= View::partial('front.products._filters', $filterPartial + ['idPrefix' => 'desk']) ?>
        </aside>

        <div class="col-lg-9">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <p class="mb-0 text-muted" role="status"><?= e(number_format($total, 0, ',', '.')) ?> ürün bulundu</p>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#ymFilterOffcanvas" aria-controls="ymFilterOffcanvas">Filtrele</button>
                    <form method="get" action="<?= e(url($baseUrl)) ?>" class="d-flex align-items-center gap-2" data-sort-form>
                        <?php foreach ($queryParams as $k => $v): ?>
                            <?php if (!in_array($k, ['sort', 'page'], true)): ?>
                                <input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <label for="ymSortSelect" class="visually-hidden">Sıralama</label>
                        <select id="ymSortSelect" name="sort" class="form-select form-select-sm" data-sort-select>
                            <?php foreach ($sortLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary btn-sm" data-sort-submit>Uygula</button>
                    </form>
                </div>
            </div>

            <?php if ($chips !== []): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Aktif filtreler">
                    <?php foreach ($chips as $chip): ?>
                        <a class="badge rounded-pill text-bg-light border text-decoration-none fw-normal py-2 px-3" href="<?= e($link($baseUrl, array_diff_key($queryParams, array_flip($chip['drop'])))) ?>" aria-label="<?= e($chip['label']) ?> filtresini kaldır">
                            <?= e($chip['label']) ?> <span aria-hidden="true">&times;</span>
                        </a>
                    <?php endforeach; ?>
                    <a class="small" href="<?= e(url($baseUrl)) ?>">Temizle</a>
                </div>
            <?php endif; ?>

            <?php if ($products['items'] === []): ?>
                <div class="text-center py-5">
                    <svg class="mb-3 text-muted" width="48" height="48" aria-hidden="true" focusable="false"><use href="#i-search"></use></svg>
                    <h2 class="h5">Aramanıza uygun ürün bulunamadı</h2>
                    <p class="text-muted">Filtreleri değiştirmeyi veya temizlemeyi deneyin.</p>
                    <a class="btn btn-success" href="<?= e(url($baseUrl)) ?>">Filtreleri temizle</a>
                </div>
            <?php else: ?>
                <div class="row row-cols-2 row-cols-md-3 g-3">
                    <?php foreach ($products['items'] as $product): ?>
                        <div class="col">
                            <?= View::partial('partials.product-card', ['product' => $product]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ((int) $pager['pages'] > 1): ?>
                    <div class="mt-4">
                        <?= View::partial('partials.pagination', ['pager' => $pager, 'baseUrl' => $baseUrl]) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="ymFilterOffcanvas" aria-labelledby="ymFilterOffcanvasLabel">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="ymFilterOffcanvasLabel">Filtrele</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body">
        <?= View::partial('front.products._filters', $filterPartial + ['idPrefix' => 'mob']) ?>
    </div>
</div>