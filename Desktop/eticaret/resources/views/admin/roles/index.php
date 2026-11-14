<?php /* resources/views/admin/roles/index.php */ ?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Roller</h1>
</div>

<?php if (empty($roles)): ?>
    <div class="empty-state text-center text-muted p-4">Tanımlı rol bulunamadı.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($roles as $role): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="admin-card h-100">
                    <div class="admin-card-header d-flex justify-content-between align-items-center">
                        <span><?= e($role['label']) ?></span>
                        <?php if ($role['is_protected']): ?>
                            <span class="badge text-bg-warning"><i class="bi bi-shield-lock"></i> Korumalı</span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-card-body d-flex flex-column">
                        <p class="text-muted small mb-1"><code><?= e($role['name']) ?></code></p>
                        <p class="mb-3"><?= e($role['description'] ?? '') ?></p>
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="fs-4 fw-semibold"><?= (int) $role['user_count'] ?></div>
                                <div class="small text-muted">Kullanıcı</div>
                            </div>
                            <div class="col-6">
                                <div class="fs-4 fw-semibold"><?= (int) $role['permission_count'] ?> / <?= (int) $totalPermissions ?></div>
                                <div class="small text-muted">Yetki</div>
                            </div>
                        </div>
                        <a class="btn btn-outline-primary mt-auto"
                           href="<?= e(url('/yonetim/roller/' . (int) $role['id'] . '/duzenle')) ?>">
                            <i class="bi bi-sliders"></i> Yetkileri <?= $role['is_protected'] ? 'görüntüle' : 'düzenle' ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>