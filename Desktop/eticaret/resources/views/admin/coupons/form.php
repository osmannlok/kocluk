<?php
// resources/views/admin/coupons/form.php
/**
 * Girdi: $title, $coupon (form değerleri), $isEdit, $stats (?array), $formAction.
 * Güvenlik: tüm çıktı e() ile; form POST + CSRF; satır içi betik/stil yok.
 * Sunucu tarafı doğrulama CouponAdminController::collect() içindedir; buradaki öznitelikler yalnızca kullanım kolaylığıdır.
 *
 * @var array<string,mixed> $coupon
 * @var bool $isEdit
 * @var array<string,mixed>|null $stats
 * @var string $formAction
 */
$err = static function (string $key): string {
    $value = errors($key);

    return is_string($value) ? $value : '';
};
$val = static fn (string $key): string => (string) old($key, (string) ($coupon[$key] ?? ''));
$type = $val('discount_type') === 'fixed' ? 'fixed' : 'percent';
$isActive = (string) old('is_active', (string) ((int) ($coupon['is_active'] ?? 1))) === '1';
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <a href="<?= e(url('/yonetim/kuponlar')) ?>" class="btn btn-outline-secondary">Listeye dön</a>
</div>

<?php if ($isEdit && is_array($stats)): ?>
    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-4">
            <div class="kpi-card kpi-accent-primary p-3">
                <div class="small text-muted">Kullanım sayısı</div>
                <div class="h5 mb-0"><?= (int) $stats['used_count'] ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="kpi-card kpi-accent-info p-3">
                <div class="small text-muted">Kullanım kaydı</div>
                <div class="h5 mb-0"><?= (int) $stats['usage_rows'] ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="kpi-card kpi-accent-success p-3">
                <div class="small text-muted">Toplam verilen indirim</div>
                <div class="h5 mb-0"><?= e(money((string) $stats['total_discount'])) ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" class="admin-card" novalidate data-dirty-guard>
    <?= csrf_field() ?>
    <div class="admin-card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="couponCode" class="form-label">Kupon kodu <span class="text-danger" aria-hidden="true">*</span></label>
                <div class="input-group">
                    <input type="text" id="couponCode" name="code" value="<?= e($val('code')) ?>"
                           class="form-control text-uppercase<?= $err('code') !== '' ? ' is-invalid' : '' ?>"
                           maxlength="40" minlength="3" pattern="[A-Za-z0-9_\-]{3,40}" required
                           autocomplete="off" aria-describedby="couponCodeHelp<?= $err('code') !== '' ? ' couponCodeError' : '' ?>">
                    <?php if (!$isEdit): ?>
                        <a href="<?= e(url('/yonetim/kuponlar/yeni?uret=1')) ?>" class="btn btn-outline-secondary">Rastgele kod üret</a>
                    <?php endif; ?>
                </div>
                <?php if ($err('code') !== ''): ?>
                    <div id="couponCodeError" class="text-danger small mt-1" role="alert"><?= e($err('code')) ?></div>
                <?php endif; ?>
                <div id="couponCodeHelp" class="form-text">3-40 karakter; yalnızca A-Z, 0-9, alt çizgi ve tire. Küçük harfler büyüğe çevrilir. "Rastgele kod üret" bağlantısı formu yeniler; önce kodu üretin, sonra diğer alanları doldurun.</div>
            </div>

            <div class="col-12 col-md-6">
                <label for="couponDescription" class="form-label">Açıklama</label>
                <input type="text" id="couponDescription" name="description" value="<?= e($val('description')) ?>"
                       class="form-control<?= $err('description') !== '' ? ' is-invalid' : '' ?>" maxlength="255"
                       aria-describedby="couponDescriptionHelp">
                <?php if ($err('description') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('description')) ?></div>
                <?php endif; ?>
                <div id="couponDescriptionHelp" class="form-text">Yalnızca yönetim panelinde görünür (örn. "Bahar kampanyası").</div>
            </div>

            <div class="col-12 col-md-4">
                <label for="couponType" class="form-label">İndirim türü <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="couponType" name="discount_type" class="form-select<?= $err('discount_type') !== '' ? ' is-invalid' : '' ?>" required>
                    <option value="percent"<?= $type === 'percent' ? ' selected' : '' ?>>Yüzde (%)</option>
                    <option value="fixed"<?= $type === 'fixed' ? ' selected' : '' ?>>Sabit tutar (₺)</option>
                </select>
                <?php if ($err('discount_type') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('discount_type')) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-4">
                <label for="couponValue" class="form-label">İndirim değeri <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" id="couponValue" name="discount_value" value="<?= e($val('discount_value')) ?>"
                       class="form-control<?= $err('discount_value') !== '' ? ' is-invalid' : '' ?>" inputmode="decimal"
                       maxlength="20" required aria-describedby="couponValueHelp">
                <?php if ($err('discount_value') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('discount_value')) ?></div>
                <?php endif; ?>
                <div id="couponValueHelp" class="form-text">Yüzde için 1-100 (örn. 10); sabit tutar için TL (örn. 50.00).</div>
            </div>

            <div class="col-12 col-md-4">
                <label for="couponMaxDiscount" class="form-label">Üst indirim sınırı (₺)</label>
                <input type="text" id="couponMaxDiscount" name="max_discount_amount" value="<?= e($val('max_discount_amount')) ?>"
                       class="form-control<?= $err('max_discount_amount') !== '' ? ' is-invalid' : '' ?>" inputmode="decimal"
                       maxlength="20" aria-describedby="couponMaxHelp">
                <?php if ($err('max_discount_amount') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('max_discount_amount')) ?></div>
                <?php endif; ?>
                <div id="couponMaxHelp" class="form-text">Yalnızca yüzde kuponlarında; boş bırakılırsa sınır yoktur.</div>
            </div>

            <div class="col-12 col-md-4">
                <label for="couponMinCart" class="form-label">Minimum sepet tutarı (₺)</label>
                <input type="text" id="couponMinCart" name="min_cart_total" value="<?= e($val('min_cart_total')) ?>"
                       class="form-control<?= $err('min_cart_total') !== '' ? ' is-invalid' : '' ?>" inputmode="decimal"
                       maxlength="20" aria-describedby="couponMinHelp">
                <?php if ($err('min_cart_total') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('min_cart_total')) ?></div>
                <?php endif; ?>
                <div id="couponMinHelp" class="form-text">Boş veya 0 ise alt sınır yoktur.</div>
            </div>

            <div class="col-12 col-md-4">
                <label for="couponUsageLimit" class="form-label">Toplam kullanım limiti</label>
                <input type="number" id="couponUsageLimit" name="usage_limit" value="<?= e($val('usage_limit')) ?>"
                       class="form-control<?= $err('usage_limit') !== '' ? ' is-invalid' : '' ?>" min="1" step="1"
                       aria-describedby="couponLimitHelp">
                <?php if ($err('usage_limit') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('usage_limit')) ?></div>
                <?php endif; ?>
                <div id="couponLimitHelp" class="form-text">Boş bırakılırsa sınırsız. En az 1 olmalı.</div>
            </div>

            <div class="col-12 col-md-4">
                <label for="couponPerUser" class="form-label">Kullanıcı başı limit</label>
                <input type="number" id="couponPerUser" name="usage_per_user" value="<?= e($val('usage_per_user')) ?>"
                       class="form-control<?= $err('usage_per_user') !== '' ? ' is-invalid' : '' ?>" min="1" step="1"
                       aria-describedby="couponPerUserHelp">
                <?php if ($err('usage_per_user') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('usage_per_user')) ?></div>
                <?php endif; ?>
                <div id="couponPerUserHelp" class="form-text">Bir müşterinin kuponu en çok kaç kez kullanabileceği. Boş = sınırsız.</div>
            </div>

            <div class="col-12 col-md-6">
                <label for="couponStartsAt" class="form-label">Başlangıç tarihi</label>
                <input type="datetime-local" id="couponStartsAt" name="starts_at" value="<?= e($val('starts_at')) ?>"
                       class="form-control<?= $err('starts_at') !== '' ? ' is-invalid' : '' ?>">
                <?php if ($err('starts_at') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('starts_at')) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label for="couponEndsAt" class="form-label">Bitiş tarihi</label>
                <input type="datetime-local" id="couponEndsAt" name="ends_at" value="<?= e($val('ends_at')) ?>"
                       class="form-control<?= $err('ends_at') !== '' ? ' is-invalid' : '' ?>" aria-describedby="couponDatesHelp">