<?php
// resources/views/admin/regions/index.php

/**
 * Değişkenler: $title, $regions (id, name, slug, sort_order, is_active), $isEdit, $region, $formAction.
 * Güvenlik: tüm çıktı e() ile; satır içi script/style/on* yok; her değiştirici işlem CSRF'li POST formudur.
 */

$canDelete = can('category.manage') && can('product.delete');
$submitted = old('name') !== null;

// Doğrulama hatasından dönüldüyse old() değerleri, aksi halde kayıt değerleri gösterilir.
$pv = static fn (string $k): string => $submitted ? (string) (old($k) ?? '') : (string) ($region[$k] ?? '');
$active = $submitted ? filter_var(old('is_active', false), FILTER_VALIDATE_BOOLEAN) : !empty($region['is_active']);

$err = static function (string $key): string {
    $e = errors($key);
    if (is_array($e)) {
        $e = reset($e);
    }

    return is_string($e) ? $e : '';
};
$nameErr = $err('name');
$slugErr = $err('slug');
$sortErr = $err('sort_order');
?>
<div class="page-header">
    <div>
        <h1 class="h4 mb-0">Yöreler</h1>
        <p class="text-muted small mb-0"><?= e((string) count($regions)) ?> yöre</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="admin-card">
            <?php if ($regions === []): ?>
                <div class="admin-card-body">
                    <div class="empty-state">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                        <p class="mb-0">Henüz yöre eklenmemiş.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="admin-card-body flush">
                    <table class="table table-cards align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Yöre</th>
                                <th scope="col" class="text-end">Sıra</th>
                                <th scope="col">Durum</th>
                                <th scope="col" class="text-end">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($regions as $r): ?>
                            <?php
                            $id = (int) $r['id'];
                            $name = (string) $r['name'];
                            $editingThis = $isEdit && (int) ($region['id'] ?? 0) === $id;
                            ?>
                            <tr<?= $editingThis ? ' class="table-active"' : '' ?>>
                                <td data-label="Yöre">
                                    <div class="fw-semibold"><?= e($name) ?></div>
                                    <div class="text-muted small"><?= e((string) $r['slug']) ?></div>
                                </td>
                                <td data-label="Sıra" class="text-end"><?= e((string) (int) $r['sort_order']) ?></td>
                                <td data-label="Durum">
                                    <span class="badge <?= $r['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $r['is_active'] ? 'Aktif' : 'Pasif' ?>
                                    </span>
                                </td>
                                <td data-label="İşlemler" class="text-end">
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= e(url('/yonetim/yoreler/' . $id . '/duzenle')) ?>"
                                           aria-label="<?= e($name . ' yöresini düzenle') ?>">
                                            <i class="bi bi-pencil" aria-hidden="true"></i> Düzenle
                                        </a>
                                        <?php if ($canDelete): ?>
                                            <form method="post" action="<?= e(url('/yonetim/yoreler/' . $id . '/sil')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        data-confirm="<?= e('"' . $name . '" yöresi kalıcı olarak silinecek. Devam edilsin mi?') ?>"
                                                        aria-label="<?= e($name . ' yöresini sil') ?>">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="admin-card">
            <div class="admin-card-header"><?= $isEdit ? 'Yöreyi düzenle' : 'Yeni yöre' ?></div>
            <div class="admin-card-body">
                <form method="post" action="<?= e(url($formAction)) ?>" data-dirty-guard novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="rName">Yöre adı</label>
                        <input class="form-control<?= $nameErr !== '' ? ' is-invalid' : '' ?>" id="rName" name="name"
                               value="<?= e($pv('name')) ?>" maxlength="100" required>
                        <?php if ($nameErr !== ''): ?><div class="invalid-feedback"><?= e($nameErr) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="rSlug">Slug (boşsa addan üretilir)</label>
                        <input class="form-control<?= $slugErr !== '' ? ' is-invalid' : '' ?>" id="rSlug" name="slug"
                               value="<?= e($pv('slug')) ?>" maxlength="120">
                        <?php if ($slugErr !== ''): ?><div class="invalid-feedback"><?= e($slugErr) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="rSort">Sıra</label>
                        <input type="number" class="form-control<?= $sortErr !== '' ? ' is-invalid' : '' ?>" id="rSort" name="sort_order"
                               value="<?= e($pv('sort_order')) ?>" min="0" step="1" placeholder="Boşsa sona eklenir">
                        <?php if ($sortErr !== ''): ?><div class="invalid-feedback"><?= e($sortErr) ?></div><?php endif; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="rActive" name="is_active" value="1"<?= $active ? ' checked' : '' ?>>
                        <label class="form-check-label" for="rActive">Vitrinde aktif</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg" aria-hidden="true"></i> Kaydet
                        </button>
                        <?php if ($isEdit): ?>
                            <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/yoreler')) ?>">İptal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>