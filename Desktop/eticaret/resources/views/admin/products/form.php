<?php
// resources/views/admin/products/form.php

/**
 * Ortak ürün formu (oluşturma + düzenleme).
 * Değişkenler: $title, $isEdit, $product, $formAction, $variants, $images, $imageLimit, $categories, $regions.
 * Güvenlik: tüm çıktı e() ile; satır içi script/style/on* yok; POST formu CSRF'lidir.
 * JS bağlantıları (admin-product-form.js): [data-variant-list], template[data-variant-template], [data-variant-add],
 * [data-variant-remove|up|down|default], [data-slug-source]/[data-slug-target], [data-price]/[data-sale-price],
 * [data-image-input][data-upload-url], [data-image-list][data-reorder-url|data-primary-url|data-delete-url|data-limit].
 * Varyant alan adları variants[İNDEKS][alan] biçimindedir; şablonda İNDEKS yerine __INDEX__ yazar.
 */

$submitted = old('name') !== null;
$productId = (int) ($product['id'] ?? 0);

// Doğrulama hatasından dönüldüyse old() değerleri, aksi halde kayıt değerleri gösterilir.
$pv = static fn (string $k): string => $submitted ? (string) (old($k) ?? '') : (string) ($product[$k] ?? '');
$chk = static fn (string $k): bool => $submitted
    ? filter_var(old($k, false), FILTER_VALIDATE_BOOLEAN)
    : !empty($product[$k]);

$err = static function (string $key): string {
    $e = errors($key);
    if (is_array($e)) {
        $e = reset($e);
    }

    return is_string($e) ? $e : '';
};

$allErrors = errors();
$allErrors = is_array($allErrors) ? $allErrors : [];

$rows = $variants;
if ($submitted) {
    $oldRows = old('variants', $variants);
    $rows = is_array($oldRows) ? $oldRows : $variants;
}
$rows = array_values(array_filter($rows, 'is_array'));

// datetime-local girdisi "Y-m-d\TH:i" ister; veritabanı değeri "Y-m-d H:i:s" gelir.
$dt = static fn (mixed $v): string => $v === null || $v === '' ? '' : str_replace(' ', 'T', substr((string) $v, 0, 16));

$field = static function (string $id, string $name, string $label, string $value, string $errKey, array $attrs = [], string $col = 'col-12 col-md-6') use ($err): void {
    $message = $errKey === '' ? '' : $err($errKey);
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . e((string) $k) . ($v === true ? '' : '="' . e((string) $v) . '"');
    }
    ?>
    <div class="<?= e($col) ?>">
        <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?></label>
        <input class="form-control<?= $message !== '' ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
               value="<?= e($value) ?>"<?= $extra ?>>
        <?php if ($message !== ''): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
    </div>
    <?php
};

$area = static function (string $id, string $name, string $label, string $value, string $errKey, int $max, int $rowsCount = 3) use ($err): void {
    $message = $err($errKey);
    ?>
    <div class="col-12">
        <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?></label>
        <textarea class="form-control<?= $message !== '' ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
                  rows="<?= e((string) $rowsCount) ?>" maxlength="<?= e((string) $max) ?>"><?= e($value) ?></textarea>
        <?php if ($message !== ''): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
    </div>
    <?php
};

$check = static function (string $id, string $name, string $label, bool $checked, array $attrs = [], string $col = 'col-6 col-md-3'): void {
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . e((string) $k) . ($v === true ? '' : '="' . e((string) $v) . '"');
    }
    ?>
    <div class="<?= e($col) ?>">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1"<?= $checked ? ' checked' : '' ?><?= $extra ?>>
            <label class="form-check-label" for="<?= e($id) ?>"><?= e($label) ?></label>
        </div>
    </div>
    <?php
};

$variantRow = static function (string $i, array $v, bool $existing) use ($field, $check, $dt): void {
    $p = 'variants.' . $i . '.';
    $n = 'variants[' . $i . ']';
    $flag = static fn (string $k, bool $d): bool => filter_var($v[$k] ?? $d, FILTER_VALIDATE_BOOLEAN);
    ?>
    <div class="card mb-3" data-variant-row>
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong data-variant-title>Varyant</strong>
            <div class="btn-group btn-group-sm" role="group" aria-label="Varyant işlemleri">
                <button type="button" class="btn btn-outline-secondary" data-variant-up aria-label="Yukarı taşı">
                    <i class="bi bi-arrow-up" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-variant-down aria-label="Aşağı taşı">
                    <i class="bi bi-arrow-down" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn btn-outline-danger" data-variant-remove aria-label="Varyantı kaldır">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($existing && ($v['id'] ?? '') !== '' && ($v['id'] ?? null) !== null): ?>
                <input type="hidden" name="<?= e($n) ?>[id]" value="<?= e((string) $v['id']) ?>">
            <?php endif; ?>
            <input type="hidden" name="<?= e($n) ?>[sort_order]" value="<?= e((string) ($v['sort_order'] ?? 0)) ?>" data-variant-sort>
            <div class="row g-3">
                <?php
                $field('v' . $i . '_name', $n . '[name]', 'Varyant adı', (string) ($v['name'] ?? ''), $p . 'name', ['maxlength' => '100', 'required' => true, 'placeholder' => 'ör. 500 g kavanoz']);
                $field('v' . $i . '_sku', $n . '[sku]', 'SKU', (string) ($v['sku'] ?? ''), $p . 'sku', ['maxlength' => '64', 'required' => true, 'pattern' => '[A-Za-z0-9._\-]+']);
                $field('v' . $i . '_barcode', $n . '[barcode]', 'Barkod', (string) ($v['barcode'] ?? ''), $p . 'barcode', ['maxlength' => '64'], 'col-12 col-md-4');
                $field('v' . $i . '_weight', $n . '[weight_grams]', 'Gramaj (g)', (string) ($v['weight_grams'] ?? ''), $p . 'weight_grams', ['type' => 'number', 'min' => '1', 'step' => '1', 'required' => true], 'col-6 col-md-4');
                $field('v' . $i . '_price', $n . '[price]', 'Fiyat (TL)', (string) ($v['price'] ?? ''), $p . 'price', ['inputmode' => 'decimal', 'required' => true, 'data-price' => true], 'col-6 col-md-4');
                $field('v' . $i . '_sale', $n . '[sale_price]', 'İndirimli fiyat (TL)', (string) ($v['sale_price'] ?? ''), $p . 'sale_price', ['inputmode' => 'decimal', 'data-sale-price' => true], 'col-6 col-md-4');
                $field('v' . $i . '_sale_start', $n . '[sale_starts_at]', 'İndirim başlangıcı', $dt($v['sale_starts_at'] ?? ''), $p . 'sale_starts_at', ['type' => 'datetime-local'], 'col-6 col-md-4');
                $field('v' . $i . '_sale_end', $n . '[sale_ends_at]', 'İndirim bitişi', $dt($v['sale_ends_at'] ?? ''), $p . 'sale_ends_at', ['type' => 'datetime-local'], 'col-6 col-md-4');
                $field('v' . $i . '_stock', $n . '[stock_quantity]', 'Stok', (string) ($v['stock_quantity'] ?? 0), $p . 'stock_quantity', ['type' => 'number', 'min' => '0', 'step' => '1'], 'col-6 col-md-4');
                $field('v' . $i . '_threshold', $n . '[low_stock_threshold]', 'Kritik stok eşiği', (string) ($v['low_stock_threshold'] ?? 5), $p . 'low_stock_threshold', ['type' => 'number', 'min' => '0', 'step' => '1'], 'col-6 col-md-4');
                $check('v' . $i . '_track', $n . '[track_stock]', 'Stok takibi', $flag('track_stock', true), [], 'col-6 col-md-3');
                $check('v' . $i . '_active', $n . '[is_active]', 'Aktif', $flag('is_active', true), [], 'col-6 col-md-3');
                $check('v' . $i . '_default', $n . '[is_default]', 'Varsayılan', $flag('is_default', false), ['data-variant-default' => true], 'col-6 col-md-3');
                ?>
            </div>
            <?php if ($existing): ?>
                <p class="form-text mb-0 mt-2">Mevcut varyantta stok değişikliği, stok hareketi olarak kaydedilir.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
};

$imageItem = static function (array $img): void {
    ?>
    <li class="border rounded p-2 text-center" data-image-item data-image-id="<?= e((string) ($img['id'] ?? '')) ?>" draggable="true">
        <img src="<?= e((string) ($img['thumb_path'] ?? '')) ?>" alt="<?= e((string) ($img['alt_text'] ?? '')) ?>"
             class="img-thumbnail object-fit-cover" width="96" height="96" loading="lazy">
        <div class="mt-1">
            <span class="badge text-bg-success" data-primary-badge<?= !empty($img['is_primary']) ? '' : ' hidden' ?>>Ana görsel</span>
        </div>
        <div class="btn-group btn-group-sm mt-1" role="group" aria-label="Görsel işlemleri">
            <button type="button" class="btn btn-outline-primary" data-image-primary>Ana yap</button>
            <button type="button" class="btn btn-outline-danger" data-image-delete>Sil</button>
        </div>
    </li>
    <?php
};
?>
<div class="page-header">
    <div>
        <h1 class="h4 mb-0"><?= e($title) ?></h1>
        <?php if ($isEdit): ?>
            <p class="text-muted small mb-0"><?= e((string) $product['name']) ?></p>
        <?php endif; ?>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/urunler')) ?>">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Ürünlere dön
    </a>
</div>

<?php if ($allErrors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Form kaydedilemedi.</strong> Lütfen aşağıdaki hataları düzeltin:
        <ul class="mb-0">
            <?php foreach ($allErrors as $message): ?>
                <li><?= e(is_array($message) ? (string) reset($message) : (string) $message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url($formAction)) ?>" data-dirty-guard novalidate>
    <?= csrf_field() ?>

    <ul class="nav nav-tabs mb-3" id="productTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tabGeneralBtn" data-bs-toggle="tab" data-bs-target="#tabGeneral" type="button"
                    role="tab" aria-controls="tabGeneral" aria-selected="true">Genel</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tabFoodBtn" data-bs-toggle="tab" data-bs-target="#tabFood" type="button"
                    role="tab" aria-controls="tabFood" aria-selected="false">Gıda bilgileri</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tabVariantsBtn" data-bs-toggle="tab" data-bs-target="#tabVariants" type="button"
                    role="tab" aria-controls="tabVariants" aria-selected="false">Varyantlar</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tabImagesBtn" data-bs-toggle="tab" data-bs-target="#tabImages" type="button"
                    role="tab" aria-controls="tabImages" aria-selected="false">Görseller</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tabSeoBtn" data-bs-toggle="tab" data-bs-target="#tabSeo" type="button"
                    role="tab" aria-controls="tabSeo" aria-selected="false">SEO</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tabGeneral" role="tabpanel" aria-labelledby="tabGeneralBtn" tabindex="0">
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="row g-3">
                        <?php
                        $field('pName', 'name', 'Ürün adı', $pv('name'), 'name', ['maxlength' => '190', 'required' => true, 'data-slug-source' => true]);
                        $field('pSlug', 'slug', 'Slug (boşsa addan üretilir)', $pv('slug'), 'slug', ['maxlength' => '220', 'data-slug-target' => true]);
                        $catMsg = $err('category_id');
                        ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="pCategory">Kategori</label>
                            <select class="form-select<?= $catMsg !== '' ? ' is-invalid' : '' ?>" id="pCategory" name="category_id" required>
                                <option value="">Seçin</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= e((string) (int) $c['id']) ?>"<?= $pv('category_id') === (string) (int) $c['id'] ? ' selected' : '' ?>>
                                        <?= e((($c['parent_id'] ?? null) !== null ? '— ' : '') . (string) $c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($catMsg !== ''): ?><div class="invalid-feedback"><?= e($catMsg) ?></div><?php endif; ?>
                        </div>
                        <?php $regMsg = $err('region_id'); ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="pRegion">Yöre</label>
                            <select class="form-select<?= $regMsg !== '' ? ' is-invalid' : '' ?>" id="pRegion" name="region_id">
                                <option value="">Belirtilmemiş</option>
                                <?php foreach ($regions as $r): ?>
                                    <option value="<?= e((string) (int) $r['id']) ?>"<?= $pv('region_id') === (string) (int) $r['id'] ? ' selected' : '' ?>>
                                        <?= e((string) $r['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($regMsg !== ''): ?><div class="invalid-feedback"><?= e($regMsg) ?></div><?php endif; ?>
                        </div>
                        <?php
                        $area('pShort', 'short_description', 'Kısa açıklama', $pv('short_description'), 'short_description', 500, 2);
                        $area('pDesc', 'description', 'Açıklama (düz metin)', $pv('description'), 'description', 60000, 8);
                        $check('pActive', 'is_active', 'Vitrinde aktif', $chk('is_active'), [], 'col-6 col-md-3');
                        $check('pFeatured', 'is_featured', 'Öne çıkan', $chk('is_featured'), [], 'col-6 col-md-3');
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabFood" role="tabpanel" aria-labelledby="tabFoodBtn" tabindex="0">
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="row g-3">
                        <?php
                        $field('pOrigin', 'origin_text', 'Menşei', $pv('origin_text'), 'origin_text', ['maxlength' => '190']);
                        $field('pProducer', 'producer_name', 'Üretici', $pv('producer_name'), 'producer_name', ['maxlength' => '190']);
                        $area('pIngredients', 'ingredients', 'İçindekiler', $pv('ingredients'), 'ingredients', 20000, 3);
                        $area('pAllergen', 'allergen_info', 'Alerjen bilgisi', $pv('allergen_info'), 'allergen_info', 20000, 3);
                        $field('pStorage', 'storage_conditions', 'Saklama koşulu', $pv('storage_conditions'), 'storage_conditions', ['maxlength' => '255']);
                        $field('pShelf', 'shelf_life_info', 'Raf ömrü bilgisi', $pv('shelf_life_info'), 'shelf_life_info', ['maxlength' => '190']);
                        $field('pTax', 'tax_rate', 'KDV oranı (%)', $pv('tax_rate') === '' ? '10.00' : $pv('tax_rate'), 'tax_rate', ['inputmode' => 'decimal', 'maxlength' => '6'], 'col-6 col-md-3');
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabVariants" role="tabpanel" aria-labelledby="tabVariantsBtn" tabindex="0">
            <?php if ($err('variants') !== ''): ?>
                <div class="alert alert-danger py-2" role="alert"><?= e($err('variants')) ?></div>
            <?php endif; ?>
            <p class="text-muted small">
                En az bir aktif ve tam bir varsayılan varyant gerekir. Formdan çıkarılan mevcut varyant silinmez, pasifleştirilir.
            </p>
            <div data-variant-list>
                <?php foreach ($rows as $i => $row): ?>
                    <?php $variantRow((string) $i, $row, true); ?>
                <?php endforeach; ?>
            </div>
            <template data-variant-template>
                <?php $variantRow('__INDEX__', [
                    'id' => null, 'name' => '', 'sku' => '', 'barcode' => '', 'weight_grams' => '', 'price' => '',
                    'sale_price' => '', 'sale_starts_at' => '', 'sale_ends_at' => '', 'stock_quantity' => 0,
                    'low_stock_threshold' => 5, 'track_stock' => 1, 'is_default' => 0, 'is_active' => 1, 'sort_order' => 0,
                ], false); ?>
            </template>
            <button type="button" class="btn btn-outline-primary" data-variant-add>
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Varyant ekle
            </button>
        </div>

        <div class="tab-pane fade" id="tabImages" role="tabpanel" aria-labelledby="tabImagesBtn" tabindex="0">
            <div class="admin-card">
                <div class="admin-card-body">
                    <?php if (!$isEdit): ?>
                        <p class="mb-0 text-muted">Görsel yüklemek için önce ürünü kaydedin.</p>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label" for="imageInput">Görsel yükle (JPG, PNG, WEBP; en çok <?= e((string) (int) $imageLimit) ?> görsel)</label>
                            <input type="file" class="form-control" id="imageInput" accept="image/jpeg,image/png,image/webp" multiple
                                   data-image-input data-upload-url="<?= e(url('/yonetim/urunler/' . $productId . '/gorsel')) ?>">
                            <progress class="w-100 mt-2" max="100" value="0" data-upload-progress hidden>0%</progress>
                            <div class="form-text text-danger" data-upload-error role="alert" hidden></div>
                        </div>
                        <p class="text-muted small">Sıralamak için görselleri sürükleyip bırakın. Ana görsel vitrinde ilk gösterilir.</p>
                        <ul class="list-unstyled d-flex flex-wrap gap-3 mb-0" data-image-list
                            data-reorder-url="<?= e(url('/yonetim/urunler/' . $productId . '/gorsel/sirala')) ?>"
                            data-primary-url="<?= e(url('/yonetim/urunler/' . $productId . '/gorsel/__ID__/ana')) ?>"
                            data-delete-url="<?= e(url('/yonetim/urunler/' . $productId . '/gorsel/__ID__/sil')) ?>"
                            data-limit="<?= e((string) (int) $imageLimit) ?>">
                            <?php foreach ($images as $img): ?>
                                <?php $imageItem($img); ?>
                            <?php endforeach; ?>
                        </ul>
                        <template data-image-template>
                            <?php $imageItem(['id' => '', 'thumb_path' => '', 'alt_text' => '', 'is_primary' => false]); ?>
                        </template>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabSeo" role="tabpanel" aria-labelledby="tabSeoBtn" tabindex="0">
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="row g-3">
                        <?php
                        $field('pMetaTitle', 'meta_title', 'Meta başlık', $pv('meta_title'), 'meta_title', ['maxlength' => '160'], 'col-12');
                        $area('pMetaDesc', 'meta_description', 'Meta açıklama', $pv('meta_description'), 'meta_description', 255, 2);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg" aria-hidden="true"></i> Kaydet
        </button>
        <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/urunler')) ?>">İptal</a>
    </div>
</form>