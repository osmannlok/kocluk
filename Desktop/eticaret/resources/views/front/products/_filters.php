<?php // resources/views/front/products/_filters.php

/**
 * Ortak filtre paneli (sidebar + offcanvas). $idPrefix tekrarlayan id'leri engeller.
 * Form GET ile gönderilir; page alanı taşınmadığı için sayfa 1'e döner.
 */

$idPrefix = $idPrefix ?? 'f';

$contains = static function (array $node, ?int $id) use (&$contains): bool {
    if ($id === null) {
        return false;
    }
    if ((int) $node['id'] === $id) {
        return true;
    }
    foreach ((array) ($node['children'] ?? []) as $child) {
        if ($contains($child, $id)) {
            return true;
        }
    }

    return false;
};

$renderTree = static function (array $nodes, int $depth) use (&$renderTree, $contains, $activeCategoryId): void {
    echo '<ul class="list-unstyled mb-0', $depth > 0 ? ' ps-3' : '', '">';
    foreach ($nodes as $node) {
        $isActive = $activeCategoryId !== null && (int) $node['id'] === (int) $activeCategoryId;
        echo '<li class="py-1"><a class="text-decoration-none', $isActive ? ' fw-bold text-success' : ' text-body', '"';
        echo ' href="', e(url('/kategori/' . $node['slug'])), '"';
        echo $isActive ? ' aria-current="page"' : '', '>', e((string) $node['name']), '</a>';
        $children = (array) ($node['children'] ?? []);
        // Yalnızca aktif dal açılır; liste kısa kalır.
        if ($children !== [] && $contains($node, $activeCategoryId !== null ? (int) $activeCategoryId : null)) {
            $renderTree($children, $depth + 1);
        }
        echo '</li>';
    }
    echo '</ul>';
};
?>
<div class="vstack gap-4">
    <nav aria-label="Kategoriler">
        <h3 class="h6 text-uppercase text-muted">Kategoriler</h3>
        <ul class="list-unstyled mb-1">
            <li class="py-1"><a class="text-decoration-none<?= $activeCategoryId === null ? ' fw-bold text-success' : ' text-body' ?>" href="<?= e(url('/urunler')) ?>">Tüm ürünler</a></li>
        </ul>
        <?php $renderTree((array) $categoryTree, 0); ?>
    </nav>

    <form method="get" action="<?= e(url($baseUrl)) ?>" data-filter-form novalidate>
        <?php if ($sort !== 'newest'): ?>
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <?php endif; ?>
        <?php if (($filters['q'] ?? '') !== ''): ?>
            <input type="hidden" name="q" value="<?= e((string) $filters['q']) ?>">
        <?php endif; ?>

        <?php if (!$regionLocked): ?>
            <div class="mb-3">
                <label class="form-label h6 text-uppercase text-muted" for="<?= e($idPrefix) ?>-yore">Yöre</label>
                <select class="form-select" id="<?= e($idPrefix) ?>-yore" name="yore" data-skip-empty>
                    <option value="">Tümü</option>
                    <?php foreach ($regions as $region): ?>
                        <option value="<?= e((string) $region['slug']) ?>"<?= (string) $activeRegionSlug === (string) $region['slug'] ? ' selected' : '' ?>><?= e((string) $region['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <fieldset class="mb-3">
            <legend class="h6 text-uppercase text-muted">Fiyat aralığı (₺)</legend>
            <div class="row g-2">
                <div class="col-6">
                    <label class="visually-hidden" for="<?= e($idPrefix) ?>-min">En düşük fiyat</label>
                    <input type="text" inputmode="decimal" class="form-control" id="<?= e($idPrefix) ?>-min" name="min_price" placeholder="En az" maxlength="10" autocomplete="off" data-price-input data-skip-empty value="<?= e((string) ($filters['min_price'] ?? '')) ?>">
                </div>
                <div class="col-6">
                    <label class="visually-hidden" for="<?= e($idPrefix) ?>-max">En yüksek fiyat</label>
                    <input type="text" inputmode="decimal" class="form-control" id="<?= e($idPrefix) ?>-max" name="max_price" placeholder="En çok" maxlength="10" autocomplete="off" data-price-input data-skip-empty value="<?= e((string) ($filters['max_price'] ?? '')) ?>">
                </div>
            </div>
            <p class="text-danger small mt-1 mb-0 d-none" role="alert" data-price-error></p>
        </fieldset>

        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="<?= e($idPrefix) ?>-stock" name="in_stock" value="1"<?= !empty($filters['in_stock']) ? ' checked' : '' ?>>
            <label class="form-check-label" for="<?= e($idPrefix) ?>-stock">Stokta olanlar</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="<?= e($idPrefix) ?>-sale" name="indirimli" value="1"<?= !empty($filters['on_sale']) ? ' checked' : '' ?>>
            <label class="form-check-label" for="<?= e($idPrefix) ?>-sale">İndirimli ürünler</label>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success">Filtrele</button>
            <a class="btn btn-outline-secondary" href="<?= e(url($baseUrl)) ?>">Temizle</a>
        </div>
    </form>
</div>