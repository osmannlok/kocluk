<?php /* resources/views/admin/users/form.php */ ?>
<?php
$isEdit = $user !== null;
$uid = $isEdit ? (int) $user['id'] : 0;
$fmt = static fn (?string $v): string => $v ? date('d.m.Y H:i', (int) strtotime($v)) : '—';
$action = $isEdit
    ? url('/yonetim/kullanicilar/' . $uid . '/guncelle')
    : url('/yonetim/kullanicilar');
$fieldErrors = static fn (string $k): string => (string) (errors($k) ?? '');
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $isEdit ? 'Kullanıcıyı düzenle' : 'Yeni kullanıcı' ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/kullanicilar')) ?>"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<?php if ($isEdit && $isLastPrivileged): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-shield-exclamation"></i>
        Bu hesap sistemdeki <strong>son aktif yönetici</strong>dir. Rolü ve durumu değiştirilemez.
    </div>
<?php endif; ?>
<?php if ($isEdit && $isSelf): ?>
    <div class="alert alert-info" role="alert">
        Kendi hesabınızı düzenliyorsunuz. Rolünüzü ve durumunuzu değiştiremezsiniz.
    </div>
<?php endif; ?>
<?php if ($isEdit && $isLocked): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-lock-fill"></i>
        Hesap <strong><?= e($fmt($user['locked_until'] ?? null)) ?></strong> tarihine kadar kilitli
        (başarısız giriş: <?= (int) ($user['failed_login_count'] ?? 0) ?>).
    </div>
<?php endif; ?>
<?php if (!$isEdit): ?>
    <div class="alert alert-info" role="alert">
        Parola burada belirlenmez. Kullanıcıya e-posta ile parola belirleme bağlantısı gönderilir.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="admin-card">
            <div class="admin-card-header">Hesap bilgileri</div>
            <div class="admin-card-body">
                <form method="post" action="<?= e($action) ?>" data-dirty-guard novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="first_name">Ad</label>
                            <input class="form-control <?= $fieldErrors('first_name') !== '' ? 'is-invalid' : '' ?>" id="first_name" name="first_name"
                                   maxlength="80" required value="<?= e(old('first_name', $user['first_name'] ?? '')) ?>">
                            <div class="invalid-feedback"><?= e($fieldErrors('first_name')) ?></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="last_name">Soyad</label>
                            <input class="form-control <?= $fieldErrors('last_name') !== '' ? 'is-invalid' : '' ?>" id="last_name" name="last_name"
                                   maxlength="80" required value="<?= e(old('last_name', $user['last_name'] ?? '')) ?>">
                            <div class="invalid-feedback"><?= e($fieldErrors('last_name')) ?></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email">E-posta</label>
                            <?php if ($isEdit): ?>
                                <input class="form-control" id="email" type="email" value="<?= e($user['email'] ?? '') ?>" readonly>
                            <?php else: ?>
                                <input class="form-control <?= $fieldErrors('email') !== '' ? 'is-invalid' : '' ?>" id="email" name="email" type="email"
                                       maxlength="190" required value="<?= e(old('email', '')) ?>">
                                <div class="invalid-feedback"><?= e($fieldErrors('email')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="phone">Telefon</label>
                            <input class="form-control <?= $fieldErrors('phone') !== '' ? 'is-invalid' : '' ?>" id="phone" name="phone" type="tel"
                                   maxlength="20" placeholder="05XXXXXXXXX" value="<?= e(old('phone', $user['phone'] ?? '')) ?>">
                            <div class="invalid-feedback"><?= e($fieldErrors('phone')) ?></div>
                        </div>

                        <?php $currentRole = (int) old('role_id', $user['role_id'] ?? 0); ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="role_id">Rol</label>
                            <?php if ($isEdit && ($isSelf || $isLastPrivileged)): ?>
                                <input type="hidden" name="role_id" value="<?= $currentRole ?>">
                            <?php endif; ?>
                            <select class="form-select <?= $fieldErrors('role_id') !== '' ? 'is-invalid' : '' ?>" id="role_id" <?= ($isEdit && ($isSelf || $isLastPrivileged)) ? 'disabled' : 'name="role_id"' ?> required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int) $role['id'] ?>" <?= $currentRole === (int) $role['id'] ? 'selected' : '' ?>>
                                        <?= e($role['label'] ?? $role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?= e($fieldErrors('role_id')) ?></div>
                            <?php if ($isEdit): ?>
                                <div class="form-text">Rol değişince kullanıcının açık oturumları kapatılır.</div>
                            <?php endif; ?>
                        </div>

                        <?php if ($isEdit): ?>
                            <?php $currentStatus = (string) old('status', $user['status'] ?? 'active'); ?>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="status">Durum</label>
                                <?php if ($isSelf || $isLastPrivileged): ?>
                                    <input type="hidden" name="status" value="<?= e($currentStatus) ?>">
                                <?php endif; ?>
                                <select class="form-select" id="status" <?= ($isSelf || $isLastPrivileged) ? 'disabled' : 'name="status"' ?>>
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $currentStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-lg"></i> <?= $isEdit ? 'Kaydet' : 'Oluştur ve bağlantı gönder' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="col-12 col-lg-5">
            <div class="admin-card mb-3">
                <div class="admin-card-header">Hesap özeti</div>
                <div class="admin-card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Rol</dt>
                        <dd class="col-7"><?= e($user['role_label'] ?? $user['role_name'] ?? '') ?></dd>
                        <dt class="col-5">Son giriş</dt>
                        <dd class="col-7"><?= e($fmt($user['last_login_at'] ?? null)) ?></dd>
                        <dt class="col-5">E-posta doğrulama</dt>
                        <dd class="col-7"><?= !empty($user['email_verified_at']) ? e($fmt($user['email_verified_at'])) : 'Doğrulanmadı' ?></dd>
                        <dt class="col-5">Kilit</dt>
                        <dd class="col-7"><?= $isLocked ? 'Kilitli' : 'Kilitli değil' ?></dd>
                    </dl>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">Güvenlik işlemleri</div>
                <div class="admin-card-body d-grid gap-2">
                    <?php if ($isLocked || (int) ($user['failed_login_count'] ?? 0) > 0): ?>
                        <form method="post" action="<?= e(url('/yonetim/kullanicilar/' . $uid . '/kilidi-ac')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-warning w-100" type="submit" data-confirm="Hesap kilidi kaldırılsın mı?">
                                <i class="bi bi-unlock"></i> Kilidi aç
                            </button>
                        </form>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('/yonetim/kullanicilar/' . $uid . '/oturumlari-kapat')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-secondary w-100" type="submit" data-confirm="Kullanıcının tüm oturumları kapatılsın mı?">
                            <i class="bi bi-box-arrow-right"></i> Tüm oturumları kapat
                        </button>
                    </form>

                    <form method="post" action="<?= e(url('/yonetim/kullanicilar/' . $uid . '/sifirlama-baglantisi')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-primary w-100" type="submit" data-confirm="Kullanıcıya parola sıfırlama bağlantısı gönderilsin mi?">
                            <i class="bi bi-envelope"></i> Parola sıfırlama bağlantısı gönder
                        </button>
                    </form>

                    <?php if (!$isSelf && !$isLastPrivileged): ?>
                        <?php if (($user['status'] ?? '') === 'suspended' || ($user['status'] ?? '') === 'pending'): ?>
                            <form method="post" action="<?= e(url('/yonetim/kullanicilar/' . $uid . '/aktif-et')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-success w-100" type="submit" data-confirm="Kullanıcı aktifleştirilsin mi?">
                                    <i class="bi bi-person-check"></i> Aktifleştir
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/yonetim/kullanicilar/' . $uid . '/askiya-al')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger w-100" type="submit" data-confirm="Kullanıcı askıya alınsın mı? Oturumları kapatılır.">
                                    <i class="bi bi-person-x"></i> Askıya al
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>