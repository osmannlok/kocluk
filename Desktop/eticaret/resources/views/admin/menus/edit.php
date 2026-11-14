<?php
// resources/views/admin/menus/edit.php
/**
 * Girdi: $title, $menu, $tree (MenuItem::treeByMenu, pasifler dahil), $linkTypes, $maxDepth,
 * $categories (Category::allFlat), $pages (Page satırları), $regions (Region::activeAll), $reorderUrl.
 * Güvenlik: tüm çıktı e() ile; ekleme/düzenleme/durum/silme POST + CSRF; satır içi betik/stil yok.
 * Sunucu tarafı doğrulama (hedef varlığı, güvenli şema, 3 seviye, döngü) MenuAdminController'dadır.
 * İstemci sözleşmesi (admin-menu.js): #menuTree[data-menu-tree][data-reorder-url][data-max-depth];
 * satır li[data-item-id]; düğmeler [data-menu-move=up|down|indent|outdent]; tutamaç [data-drag-handle];
 * tür seçici select[data-link-type]; hedef grupları [data-target-group=url|category|page|region]
 * (her grupta name="link_value" alanı; yalnızca etkin grubun alanı gönderilir, diğerleri disabled).
 * Sıralama JSON'u: {"items":[{"id":int,"parent_id":int|null}]} (başlık X-CSRF-Token).
 *
 * @var string $title
 * @var array<string,mixed> $menu
 * @var array<int,array<string,mixed>> $tree
 * @var array<string,string> $linkTypes
 * @var int $maxDepth
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,array<string,mixed>> $pages
 * @var array<int,array<string,mixed>> $regions
 * @var string $reorderUrl
 */
$menuId = (int) $menu['id'];
$err = static function (string $key): string {
    $value = errors($key);

    return is_string($value) ? $value : '';
};

// Üst öğe seçici için düz liste (yalnızca yeni öğenin 3. seviyeyi aşmayacağı öğeler).
$parentOptions = [];
$flatten = static function (array $nodes, int $depth) use (&$flatten, &$parentOptions, $maxDepth): void {
    foreach ($nodes as $node) {
        if ($depth < $maxDepth) {
            $parentOptions[] = ['id' => (int) $node['id'], 'label' => str_repeat('— ', $depth - 1) . (string) $node['label']];
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            $flatten($node['children'], $depth + 1);
        }
    }
};
$flatten($tree, 1);

/**
 * Ekleme ve satır içi düzenleme formlarının ortak alanları.
 * $p: id öneki; $v: değerler (label, link_type, link_value, icon, badge_text, is_mega, open_new_tab).
 */
$fields = static function (string $p, array $v, bool $showErrors) use ($linkTypes, $categories, $pages, $regions, $err): void {
    $type = isset($linkTypes[(string) ($v['link_type'] ?? '')]) ? (string) $v['link_type'] : 'url';
    $value = (string) ($v['link_value'] ?? '');
    $fe = static fn (string $key): string => $showErrors ? $err($key) : '';
    ?>
    <div class="row g-2">
        <div class="col-12 col-md-4">
            <label for="<?= e($p) ?>Label" class="form-label">Etiket <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="text" id="<?= e($p) ?>Label" name="label" value="<?= e((string) ($v['label'] ?? '')) ?>"
                   class="form-control<?= $fe('label') !== '' ? ' is-invalid' : '' ?>" maxlength="100" required>
            <?php if ($fe('label') !== ''): ?>
                <div class="text-danger small mt-1" role="alert"><?= e($fe('label')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-12 col-md-3">
            <label for="<?= e($p) ?>Type" class="form-label">Bağlantı türü</label>
            <select id="<?= e($p) ?>Type" name="link_type" class="form-select<?= $fe('link_type') !== '' ? ' is-invalid' : '' ?>" data-link-type required>
                <?php foreach ($linkTypes as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $type === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($fe('link_type') !== ''): ?>
                <div class="text-danger small mt-1" role="alert"><?= e($fe('link_type')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-12 col-md-5">
            <span class="form-label d-block">Hedef</span>
            <div data-target-group="url"<?= $type === 'url' ? '' : ' hidden' ?>>
                <label for="<?= e($p) ?>Url" class="visually-hidden">Bağlantı adresi</label>
                <input type="text" id="<?= e($p) ?>Url" name="link_value" value="<?= $type === 'url' ? e($value) : '' ?>"
                       class="form-control<?= $type === 'url' && $fe('link_value') !== '' ? ' is-invalid' : '' ?>" maxlength="255"
                       placeholder="/urunler veya https://..." inputmode="url" autocomplete="off"<?= $type === 'url' ? '' : ' disabled' ?>>
            </div>
            <div data-target-group="category"<?= $type === 'category' ? '' : ' hidden' ?>>
                <label for="<?= e($p) ?>Category" class="visually-hidden">Kategori</label>
                <select id="<?= e($p) ?>Category" name="link_value" class="form-select<?= $type === 'category' && $fe('link_value') !== '' ? ' is-invalid' : '' ?>"<?= $type === 'category' ? '' : ' disabled' ?>>
                    <option value="">Kategori seçin</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c['slug']) ?>"<?= $type === 'category' && $value === (string) $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div data-target-group="page"<?= $type === 'page' ? '' : ' hidden' ?>>
                <label for="<?= e($p) ?>Page" class="visually-hidden">Sayfa</label>
                <select id="<?= e($p) ?>Page" name="link_value" class="form-select<?= $type === 'page' && $fe('link_value') !== '' ? ' is-invalid' : '' ?>"<?= $type === 'page' ? '' : ' disabled' ?>>
                    <option value="">Sayfa seçin</option>
                    <?php foreach ($pages as $pg): ?>
                        <option value="<?= e($pg['slug']) ?>"<?= $type === 'page' && $value === (string) $pg['slug'] ? ' selected' : '' ?>><?= e($pg['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div data-target-group="region"<?= $type === 'region' ? '' : ' hidden' ?>>
                <label for="<?= e($p) ?>Region" class="visually-hidden">Yöre</label>
                <select id="<?= e($p) ?>Region" name="link_value" class="form-select<?= $type === 'region' && $fe('link_value') !== '' ? ' is-invalid' : '' ?>"<?= $type === 'region' ? '' : ' disabled' ?>>
                    <option value="">Yöre seçin</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= e($r['slug']) ?>"<?= $type === 'region' && $value === (string) $r['slug'] ? ' selected' : '' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div data-target-group="products"<?= $type === 'products' ? '' : ' hidden' ?>>
                <span class="form-text">Hedef gerekmez; /urunler sayfasına gider.</span>
            </div>
            <?php if ($fe('link_value') !== ''): ?>
                <div class="text-danger small mt-1" role="alert"><?= e($fe('link_value')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-6 col-md-3">
            <label for="<?= e($p) ?>Icon" class="form-label">Simge</label>
            <input type="text" id="<?= e($p) ?>Icon" name="icon" value="<?= e((string) ($v['icon'] ?? '')) ?>"
                   class="form-control<?= $fe('icon') !== '' ? ' is-invalid' : '' ?>" maxlength="60" autocomplete="off">
            <?php if ($fe('icon') !== ''): ?>
                <div class="text-danger small mt-1" role="alert"><?= e($fe('icon')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-6 col-md-3">
            <label for="<?= e($p) ?>Badge" class="form-label">Rozet</label>
            <input type="text" id="<?= e($p) ?>Badge" name="badge_text" value="<?= e((string) ($v['badge_text'] ?? '')) ?>"
                   class="form-control<?= $fe('badge_text') !== '' ? ' is-invalid' : '' ?>" maxlength="20" autocomplete="off">
            <?php if ($fe('badge_text') !== ''): ?>
                <div class="text-danger small mt-1" role="alert"><?= e($fe('badge_text')) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-12 col-md-6 d-flex flex-wrap align-items-end gap-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="<?= e($p) ?>Mega" name="is_mega" value="1"<?= !empty($v['is_mega']) && (string) $v['is_mega'] !== '0' ? ' checked' : '' ?>>
                <label class="form-check-label" for="<?= e($p) ?>Mega">Mega menü</label>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="<?= e($p) ?>Tab" name="open_new_tab" value="1"<?= !empty($v['open_new_tab']) && (string) $v['open_new_tab'] !== '0' ? ' checked' : '' ?>>
                <label class="form-check-label" for="<?= e($p) ?>Tab">Yeni sekmede aç</label>
            </div>
        </div>
    </div>
    <?php
};

/** Ağacı yinelemeli çizer. */
$renderTree = static function (array $nodes, int $depth) use (&$renderTree, $fields, $menuId, $maxDepth): void {
    ?>
    <ul class="list-unstyled mb-0<?= $depth > 1 ? ' ms-4 mt-2' : '' ?>" data-menu-list>
    <?php foreach ($nodes as $node): ?>
        <?php
        $itemId = (int) $node['id'];
        $isActive = (int) $node['is_active'] === 1;
        $children = !empty($node['children']) && is_array($node['children']) ? $node['children'] : [];
        $base = '/yonetim/menuler/' . $menuId . '/ogeler/' . $itemId;
        ?>
        <li class="mb-2" data-item-id="<?= $itemId ?>">
            <div class="admin-card" data-menu-row>
                <div class="admin-card-body d-flex flex-wrap align-items-center gap-2 p-2">
                    <span class="btn btn-sm btn-outline-secondary" data-drag-handle title="Sürükleyerek taşıyın" aria-hidden="true">&#9776;</span>
                    <div class="flex-grow-1">
                        <span class="fw-semibold"><?= e($node['label']) ?></span>
                        <?php if (!empty($node['badge_text'])): ?>
                            <span class="badge text-bg-info"><?= e($node['badge_text']) ?></span>
                        <?php endif; ?>
                        <?php if ((int) $node['is_mega'] === 1): ?>
                            <span class="badge text-bg-light">Mega</span>
                        <?php endif; ?>
                        <?php if (!$isActive): ?>
                            <span class="badge text-bg-secondary">Gizli</span>
                        <?php endif; ?>
                        <div class="small text-muted text-break"><?= e($node['url'] ?? '') ?></div>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Sıralama: <?= e($node['label']) ?>">
                        <button type="button" class="btn btn-outline-secondary" data-menu-move="up" aria-label="Yukarı taşı">&uarr;</button>
                        <button type="button" class="btn btn-outline-secondary" data-menu-move="down" aria-label="Aşağı taşı">&darr;</button>
                        <button type="button" class="btn btn-outline-secondary" data-menu-move="outdent" aria-label="Bir seviye çıkar">&larr;</button>
                        <button type="button" class="btn btn-outline-secondary" data-menu-move="indent" aria-label="Bir seviye gir">&rarr;</button>
                    </div>
                    <div class="d-inline-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse"
                                data-bs-target="#menuItemEdit<?= $itemId ?>" aria-expanded="false"
                                aria-controls="menuItemEdit<?= $itemId ?>">Düzenle</button>
                        <form method="post" action="<?= e(url($base . '/durum')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $isActive ? 'Gizle' : 'Göster' ?></button>
                        </form>
                        <form method="post" action="<?= e(url($base . '/sil')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    data-confirm="Bu öğe<?= $children !== [] ? ' ve alt öğeleri' : '' ?> kalıcı olarak silinsin mi?">Sil</button>
                        </form>
                    </div>
                </div>
                <div class="collapse border-top" id="menuItemEdit<?= $itemId ?>">
                    <form method="post" action="<?= e(url($base . '/guncelle')) ?>" class="p-3" novalidate>
                        <?= csrf_field() ?>
                        <?php $fields('item' . $itemId, $node, false); ?>
                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
                            <button type="button" class="btn btn-sm btn-link" data-bs-toggle="collapse"
                                    data-bs-target="#menuItemEdit<?= $itemId ?>">Vazgeç</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php if ($children !== [] && $depth < $maxDepth): ?>
                <?php $renderTree($children, $depth + 1); ?>
            <?php else: ?>
                <ul class="list-unstyled mb-0 ms-4 mt-2" data-menu-list></ul>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>
    <?php
};
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <a href="<?= e(url('/yonetim/menuler')) ?>" class="btn btn-outline-secondary">Menülere dön</a>
</div>

<section class="admin-card mb-4" aria-labelledby="menuTreeTitle">
    <div class="admin-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 class="h6 mb-0" id="menuTreeTitle">Menü öğeleri</h2>
        <span class="small text-muted">Sürükleyin veya ok düğmelerini kullanın. En çok <?= (int) $maxDepth ?> seviye.</span>
    </div>
    <div class="admin-card-body">
        <div class="visually-hidden" role="status" aria-live="polite" data-menu-status></div>
        <?php if ($tree === []): ?>
            <div class="empty-state p-3 text-center text-muted">Bu menüde henüz öğe yok.</div>
        <?php else: ?>
            <div id="menuTree" data-menu-tree data-reorder-url="<?= e($reorderUrl) ?>" data-max-depth="<?= (int) $maxDepth ?>">
                <?php $renderTree($tree, 1); ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="admin-card" aria-labelledby="menuAddTitle">
    <div class="admin-card-header">
        <h2 class="h6 mb-0" id="menuAddTitle">Yeni öğe ekle</h2>
    </div>
    <form method="post" action="<?= e(url('/yonetim/menuler/' . $menuId . '/ogeler')) ?>" novalidate data-dirty-guard>
        <?= csrf_field() ?>
        <div class="admin-card-body">
            <?php
            $fields('new', [
                'label' => old('label', ''),
                'link_type' => old('link_type', 'url'),
                'link_value' => old('link_value', ''),
                'icon' => old('icon', ''),
                'badge_text' => old('badge_text', ''),
                'is_mega' => old('is_mega', '0'),
                'open_new_tab' => old('open_new_tab', '0'),
            ], true);
            ?>
            <div class="row g-2 mt-1">
                <div class="col-12 col-md-6">
                    <label for="newParent" class="form-label">Üst öğe</label>
                    <select id="newParent" name="parent_id" class="form-select<?= $err('parent_id') !== '' ? ' is-invalid' : '' ?>">
                        <option value="">Yok (en üst seviye)</option>
                        <?php foreach ($parentOptions as $opt): ?>
                            <option value="<?= (int) $opt['id'] ?>"<?= (string) old('parent_id', '') === (string) $opt['id'] ? ' selected' : '' ?>><?= e($opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($err('parent_id') !== ''): ?>
                        <div class="text-danger small mt-1" role="alert"><?= e($err('parent_id')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="admin-card-footer p-3 border-top">
            <button type="submit" class="btn btn-primary">Öğeyi ekle</button>
        </div>
    </form>
</section>