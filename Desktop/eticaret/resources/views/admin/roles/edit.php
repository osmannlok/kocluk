<?php /* resources/views/admin/roles/edit.php */ ?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Yetki matrisi: <?= e($role['label'] ?? $role['name']) ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/roller')) ?>"><i class="bi bi-arrow-left"></i> Rollere dön</a>
</div>

<?php if ($isLocked): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-shield-lock"></i>
        Bu rol korumalıdır: yetkileri azaltılamaz ve burada değiştirilemez.
    </div>
<?php else: ?>
    <div class="alert alert-info" role="alert">
        Hassas yetkiler (kullanıcı, rol, ayar, denetim, iade, ürün silme) başka role verilirse güvenlik olayı olarak kaydedilir.
    </div>
<?php endif; ?>

<?php $permError = (string) (errors('permission_ids') ?? ''); ?>
<?php if ($permError !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($permError) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/yonetim/roller/' . (int) $role['id'] . '/guncelle')) ?>" data-dirty-guard>
    <?= csrf_field() ?>
    <?php foreach ($groups as $module => $perms): ?>
        <div class="admin-card mb-3">
            <div class="admin-card-header text-capitalize"><?= e($module) ?></div>
            <div class="admin-card-body">
                <div class="row g-2">
                    <?php foreach ($perms as $perm): ?>
                        <?php
                        $checked = $isLocked || in_array($perm['code'], $selectedCodes, true);
                        $inputId = 'perm-' . (int) $perm['id'];
                        ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="<?= e($inputId) ?>"
                                       name="permission_ids[]" value="<?= (int) $perm['id'] ?>"
                                       <?= $checked ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                                <label class="form-check-label" for="<?= e($inputId) ?>">
                                    <?= e($perm['label'] ?? $perm['code']) ?>
                                    <span class="d-block small text-muted"><code><?= e($perm['code']) ?></code></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!$isLocked): ?>
        <button class="btn btn-primary" type="submit" data-confirm="Rol yetkileri kaydedilsin mi? Değişiklik denetim günlüğüne yazılır.">
            <i class="bi bi-check-lg"></i> Kaydet
        </button>
    <?php endif; ?>
</form>