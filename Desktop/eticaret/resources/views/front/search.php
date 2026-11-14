<?php // resources/views/front/search.php

use App\Core\View;

$sortLabels = [
    'newest' => 'En yeni',
    'price_asc' => 'Fiyat: Düşükten yükseğe',
    'price_desc' => 'Fiyat: Yüksekten düşüğe',
    'name' => 'İsme göre (A-Z)',
];
$total = (int) $products['total'];
?>
<div class="container py-3 py-lg-4" data-catalog-list>
    <?= View::partial('partials.breadcrumb', ['crumbs' => $crumbs]) ?>

    <header class="mb-3">
        <h1 class="h3 mb-1"><?= $q !== '' ? '“' . e($q) . '” için arama sonuçları' : 'Ürün ara' ?></h1>
        <?php if ($q !== ''): ?>
            <p class="text-muted mb-0" role="status"><?= e(number_format($total, 0, ',', '.')) ?> ürün bulundu</p>
        <?php endif; ?>
    </header>

    <?php if ($q === ''): ?>
        <div class="alert alert-info" role="status">
            <?= $tooShort ? 'Arama için en az 2 karakter girin.' : 'Aramak istediğiniz ürünü yazın.' ?>
        </div>
    <?php endif; ?>

    <?php if ($q !== '' && $products['items'] !== []): ?>
        <form method="get" action="<?= e(url('/ara')) ?>" class="d-flex align-items-center justify-content-end gap-2 mb-3" data-sort-form>
            <input type="hidden" name="q" value="<?= e($q) ?>">
            <label for="ymSortSelect" class="visually-hidden">Sıralama</label>
            <select id="ymSortSelect" name="sort" class="form-select form-select-sm w-auto" data-sort-select>
                <?php foreach ($sortLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-secondary btn-sm" data-sort-submit>Uygula</button>
        </form>

        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            <?php foreach ($products['items'] as $product): ?>
                <div class="col"><?= View::partial('partials.product-card', ['product' => $product]) ?></div>
            <?php endforeach; ?>
        </div>
        <?php if ((int) $pager['pages'] > 1): ?>
            <div class="mt-4"><?= View::partial('partials.pagination', ['pager' => $pager, 'baseUrl' => '/ara']) ?></div>
        <?php endif; ?>
    <?php elseif ($q !== ''): ?>
        <div class="text-center py-4">
            <svg class="mb-3 text-muted" width="48" height="48" aria-hidden="true" focusable="false"><use href="#i-search"></use></svg>
            <h2 class="h5">Sonuç bulunamadı</h2>
            <p class="text-muted">Yazımı kontrol edin veya aşağıdaki kategorilere göz atın.</p>
        </div>
    <?php endif; ?>

    <?php if ($q === '' || $products['items'] === []): ?>
        <?php if ($suggestCategories !== []): ?>
            <section class="mt-3" aria-labelledby="ymSuggestCats">
                <h2 class="h6 text-uppercase text-muted" id="ymSuggestCats">Önerilen kategoriler</h2>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($suggestCategories as $cat): ?>
                        <a class="btn btn-outline-success rounded-pill" href="<?= e(url('/kategori/' . $cat['slug'])) ?>"><?= e((string) $cat['name']) ?></a>
                    <?php endforeach; ?>
                    <a class="btn btn-success rounded-pill" href="<?= e(url('/urunler')) ?>">Tüm ürünler</a>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>