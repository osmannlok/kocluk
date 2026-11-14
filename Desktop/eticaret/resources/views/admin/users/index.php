<?php /* resources/views/admin/users/index.php */ ?>
<?php
$fmt = static fn (?string $v): string => $v ? date('d.m.Y H:i', (int) strtotime($v)) : '—';
$statusClass = ['active' => 'success', 'suspended' => 'danger', 'pending' => 'warning'];
$now = time();
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Kullanıcılar</h1>
    <a class="btn btn-primary" href="<?= e(url('/yonetim/kullanicilar/olustur')) ?>">
        <i class="bi bi-person-plus"></i> Yeni kullanıcı
    </a>
</div>

<form class="filter-bar row g-2 mb-3" method="get" action="<?= e(url('/yonetim/kullanicilar')) ?>">
    <div class="col-12 col-md-5">
        <label class="visually-hidden" for="f-q">Ara</label>
        <input type="search" class="form-control" id="f-q" name="q" maxlength="100"
               placeholder="Ad, soyad veya e-posta ara" value="<?= e($filters['q'] ?? '') ?>">
    </div>
    <div class="col-6 col-md-3">
        <label class="visually-hidden" for="f-role">Rol</label>
        <select class="form-select" id="f-role" name="role_id">
            <option value="">Tüm roller</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= (int) $role['id'] ?>" <?= (int) ($filters['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                    <?= e($role['label'] ?? $role['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="visually-hidden" for="f-status">Durum</label>
        <select class="form-select" id="f-status" name="status">
            <option value="">Tüm durumlar</option>
            <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrele</button>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-body flush">
        <?php if (empty($users)): ?>
            <div class="empty-state p-4 text-center text-muted">Kriterlere uyan kullanıcı bulunamadı.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-cards">
                    <thead>
                        <tr>
                            <th>Ad Soyad</th>
                            <th>E-posta</th>
                            <th>Rol</th>
                            <th>Durum</th>
                            <th>Son giriş</th>
                            <th>Doğrulama</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php $locked = !empty($u['locked_until']) && strtotime((string) $u['locked_until']) > $now; ?>
                        <tr>
                            <td data-label="Ad Soyad">
                                <?= e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?>
                                <?php if ((int) $u['id'] === (int) $currentUserId): ?>
                                    <span class="badge text-bg-light border">Siz</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="E-posta"><?= e($u['email'] ?? '') ?></td>
                            <td data-label="Rol"><span class="badge text-bg-dark"><?= e($u['role_label'] ?? $u['role_name'] ?? '') ?></span></td>
                            <td data-label="Durum">
                                <span class="badge text-bg-<?= e($statusClass[$u['status'] ?? ''] ?? 'secondary') ?>">
                                    <?= e($statuses[$u['status'] ?? ''] ?? ($u['status'] ?? '')) ?>
                                </span>
                                <?php if ($locked): ?>
                                    <span class="badge text-bg-danger"><i class="bi bi-lock-fill"></i> Kilitli</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Son giriş"><?= e($fmt($u['last_login_at'] ?? null)) ?></td>
                            <td data-label="Doğrulama">
                                <?php if (!empty($u['email_verified_at'])): ?>
                                    <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                                    <span class="visually-hidden">Doğrulandı</span> <?= e($fmt($u['email_verified_at'])) ?>
                                <?php else: ?>
                                    <i class="bi bi-x-circle text-muted" aria-hidden="true"></i>
                                    <span class="text-muted">Doğrulanmadı</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="İşlem" class="text-end">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= e(url('/yonetim/kullanicilar/' . (int) $u['id'] . '/duzenle')) ?>">
                                    <i class="bi bi-pencil"></i> Düzenle
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= \App\Core\View::partial('partials.admin-pagination', ['pager' => $pager]) ?>