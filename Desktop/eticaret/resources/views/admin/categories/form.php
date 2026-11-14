<?php
// resources/views/admin/categories/form.php

/**
 * Ortak kategori formu (oluşturma + düzenleme).
 * Değişkenler: $title, $isEdit, $category, $formAction, $parentOptions (id, name, depth).
 * Güvenlik: tüm çıktı e() ile; satır içi script/style/on* yok; POST formu CSRF'lidir; dosya yükleme
 * doğrulaması ve yeniden encode sunucuda ImageUploadService'tedir (burada yalnızca accept ipucu var).
 */

$submitted = old('name') !== null;

// Doğrulama hatasından dönüldüyse old() değerleri, aksi halde kayıt değerleri gösterilir.
$pv = static fn (string $k): string => $submitted ? (string) (old($k) ?? '') : (string) ($category[$k] ?? '');
$active = $submitted ? filter_var(old('is_active', false), FILTER_VALIDATE_BOOLEAN) : !empty($category['is_active']);

$err = static function (string $key): string {
    $e = errors($key);
    if (is_array($e)) {
        $e = reset($e);
    }

    return is_string($e) ? $e : '';
};

$allErrors = errors();
$allErrors = is_array($allErrors) ? $allErrors : [];

$selectedParent = $pv('parent_id');
$currentImage = $isEdit && !empty($category['image_path']) ? (string) $category['image_path'] : '';

$field = static function (string $id, string $name, string $label, string $value, string $errKey, array $attrs = [], string $col = 'col-12 col-md-6') use ($err): void {
    $message = $err($errKey);
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
?>
<div class="page-header">
    <div>
        <h1 class="h4 mb-0"><?= e($title) ?></h1>
        <?php if ($isEdit): ?>
            <p class="text-muted small mb-0"><?= e((string) $category['name']) ?></p>
        <?php endif; ?>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/kategoriler')) ?>">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Kategorilere dön
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

<form method="post" action="<?= e(url($formAction)) ?>" enctype="multipart/form-data" data-dirty-guard novalidate>
    <?= csrf_field() ?>

    <div class="admin-card">
        <div class="admin-card-header">Genel</div>
        <div class="admin-card-body">
            <div class="row g-3">
                <?php
                $field('cName', 'name', 'Kategori adı', $pv('name'), 'name', ['maxlength' => '120', 'required' => true]);
                $field('cSlug', 'slug', 'Slug (boşsa addan üretilir)', $pv('slug'), 'slug', ['maxlength' => '140']);
                $parentMsg = $err('parent_id');
                ?>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="cParent">Üst kategori</label>
                    <select class="form-select<?= $parentMsg !== '' ? ' is-invalid' : '' ?>" id="cParent" name="parent_id">
                        <option value="">Ana kategori (üst kategori yok)</option>
                        <?php foreach ($parentOptions as $o): ?>
                            <option value="<?= e((string) (int) $o['id']) ?>"<?= $selectedParent === (string) (int) $o['id'] ? ' selected' : '' ?>>
                                <?= e(str_repeat('— ', (int) $o['depth']) . (string) $o['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($parentMsg !== ''): ?><div class="invalid-feedback"><?= e($parentMsg) ?></div><?php endif; ?>
                </div>
                <?php $field('cSort', 'sort_order', 'Sıra', $pv('sort_order'), 'sort_order', ['type' => 'number', 'min' => '0', 'step' => '1', 'placeholder' => 'Boşsa sona eklenir'], 'col-6 col-md-3'); ?>
                <div class="col-6 col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="cActive" name="is_active" value="1"<?= $active ? ' checked' : '' ?>>
                        <label class="form-check-label" for="cActive">Vitrinde aktif</label>
                    </div>
                </div>
                <?php $descMsg = $err('description'); ?>
                <div class="col-12">
                    <label class="form-label" for="cDesc">Açıklama (düz metin)</label>
                    <textarea class="form-control<?= $descMsg !== '' ? ' is-invalid' : '' ?>" id="cDesc" name="description"
                              rows="4" maxlength="5000"><?= e($pv('description')) ?></textarea>
                    <?php if ($descMsg !== ''): ?><div class="invalid-feedback"><?= e($descMsg) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card mt-3">
        <div class="admin-card-header">Görsel</div>
        <div class="admin-card-body">
            <?php if ($currentImage !== ''): ?>
                <div class="mb-3">
                    <img src="<?= e($currentImage) ?>" alt="<?= e((string) $category['name']) ?>"
                         class="img-thumbnail object-fit-cover" width="120" height="120" loading="lazy">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="cRemoveImage" name="remove_image" value="1">
                        <label class="form-check-label" for="cRemoveImage">Mevcut görseli kaldır</label>
                    </div>
                </div>
            <?php endif; ?>
            <?php $imgMsg = $err('image'); ?>
            <label class="form-label" for="cImage"><?= $currentImage !== '' ? 'Görseli değiştir' : 'Görsel yükle' ?> (JPG, PNG, WEBP)</label>
            <input type="file" class="form-control<?= $imgMsg !== '' ? ' is-invalid' : '' ?>" id="cImage" name="image"
                   accept="image/jpeg,image/png,image/webp">
            <?php if ($imgMsg !== ''): ?><div class="invalid-feedback"><?= e($imgMsg) ?></div><?php endif; ?>
            <div class="form-text">Yeni görsel seçerseniz mevcut görselin yerine geçer.</div>
        </div>
    </div>

    <div class="admin-card mt-3">
        <div class="admin-card-header">SEO</div>
        <div class="admin-card-body">
            <div class="row g-3">
                <?php
                $field('cMetaTitle', 'meta_title', 'Meta başlık', $pv('meta_title'), 'meta_title', ['maxlength' => '160'], 'col-12');
                $field('cMetaDesc', 'meta_description', 'Meta açıklama', $pv('meta_description'), 'meta_description', ['maxlength' => '255'], 'col-12');
                ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg" aria-hidden="true"></i> Kaydet
        </button>
        <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/kategoriler')) ?>">İptal</a>
    </div>
</form>