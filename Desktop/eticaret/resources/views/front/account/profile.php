<?php
// resources/views/front/account/profile.php

/**
 * @var array<string,mixed> $user
 * @var bool $focusPassword
 */

$ymErr = static function (string $field): string {
    $message = errors($field);
    if (is_array($message)) {
        $message = (string) ($message[0] ?? '');
    }
    return is_string($message) ? $message : '';
};

$ymMarketing = (string) old('marketing_consent', (string) (int) ($user['marketing_consent'] ?? 0)) === '1';
?>
<h1 class="h3 mb-3">Profilim</h1>

<section class="card shadow-sm mb-4" aria-labelledby="ymProfileTitle">
    <div class="card-body">
        <h2 class="h5 mb-3" id="ymProfileTitle">Kişisel bilgiler</h2>

        <form method="post" action="<?= e(url('/hesabim/profil')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="ymFirstName" class="form-label">Ad</label>
                    <input type="text" id="ymFirstName" name="first_name" required maxlength="80" autocomplete="given-name"
                           class="form-control<?= $ymErr('first_name') !== '' ? ' is-invalid' : '' ?>"
                           value="<?= e(old('first_name', $user['first_name'] ?? '')) ?>">
                    <?php if ($ymErr('first_name') !== ''): ?><div class="invalid-feedback d-block"><?= e($ymErr('first_name')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="ymLastName" class="form-label">Soyad</label>
                    <input type="text" id="ymLastName" name="last_name" required maxlength="80" autocomplete="family-name"
                           class="form-control<?= $ymErr('last_name') !== '' ? ' is-invalid' : '' ?>"
                           value="<?= e(old('last_name', $user['last_name'] ?? '')) ?>">
                    <?php if ($ymErr('last_name') !== ''): ?><div class="invalid-feedback d-block"><?= e($ymErr('last_name')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="ymEmail" class="form-label">E-posta</label>
                    <input type="email" id="ymEmail" class="form-control" value="<?= e($user['email'] ?? '') ?>"
                           disabled aria-describedby="ymEmailHelp">
                    <div id="ymEmailHelp" class="form-text">E-posta adresi bu ekranda değiştirilemez.</div>
                </div>
                <div class="col-md-6">
                    <label for="ymPhone" class="form-label">Telefon</label>
                    <input type="tel" id="ymPhone" name="phone" maxlength="20" inputmode="tel" autocomplete="tel"
                           placeholder="05XXXXXXXXX" aria-describedby="ymPhoneHelp"
                           class="form-control<?= $ymErr('phone') !== '' ? ' is-invalid' : '' ?>"
                           value="<?= e(old('phone', $user['phone'] ?? '')) ?>">
                    <div id="ymPhoneHelp" class="form-text">Örn. 05XXXXXXXXX</div>
                    <?php if ($ymErr('phone') !== ''): ?><div class="invalid-feedback d-block"><?= e($ymErr('phone')) ?></div><?php endif; ?>
                </div>
                <div class="col-12">
                    <input type="hidden" name="marketing_consent" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="ymMarketing"
                               name="marketing_consent" value="1" <?= $ymMarketing ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ymMarketing">
                            Kampanya ve duyurulardan e-posta/SMS ile haberdar olmak istiyorum.
                        </label>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Bilgilerimi kaydet</button>
            </div>
        </form>
    </div>
</section>

<section class="card shadow-sm" id="sifre" aria-labelledby="ymPasswordTitle">
    <div class="card-body">
        <h2 class="h5 mb-3" id="ymPasswordTitle">Parola değiştir</h2>

        <form method="post" action="<?= e(url('/hesabim/sifre')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="ymCurrentPassword" class="form-label">Mevcut parola</label>
                    <input type="password" id="ymCurrentPassword" name="current_password" required maxlength="200"
                           autocomplete="current-password" <?= $focusPassword ? 'autofocus' : '' ?>
                           class="form-control<?= $ymErr('current_password') !== '' ? ' is-invalid' : '' ?>">
                    <?php if ($ymErr('current_password') !== ''): ?><div class="invalid-feedback d-block"><?= e($ymErr('current_password')) ?></div><?php endif; ?>
                </div>
                <div class="w-100 m-0"></div>
                <div class="col-md-6">
                    <label for="ymNewPassword" class="form-label">Yeni parola</label>
                    <input type="password" id="ymNewPassword" name="new_password" required maxlength="200"
                           autocomplete="new-password" aria-describedby="ymNewPasswordHelp"
                           class="form-control<?= $ymErr('new_password') !== '' ? ' is-invalid' : '' ?>">
                    <div id="ymNewPasswordHelp" class="form-text">En az 8 karakter olmalıdır; diğer kurallar kaydederken denetlenir.</div>
                    <?php if ($ymErr('new_password') !== ''): ?><div class="invalid-feedback d-block"><?= e($ymErr('new_password')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="ymNewPasswordConfirm" class="form-label">Yeni parola (tekrar)</label>
                    <input type="password" id="ymNewPasswordConfirm" name="new_password_confirmation" required maxlength="200"
                           autocomplete="new-password"
                           class="form-control<?= $ymErr('new_password_confirmation') !== '' ? ' is-invalid' : '' ?>">
                    <?php if ($ymErr('new_password_confirmation') !== ''): ?><div class="invalid-feedback d-block"><?= e($ymErr('new_password_confirmation')) ?></div><?php endif; ?>
                </div>
            </div>
            <p class="small text-body-secondary mt-3 mb-0">
                Parolanız değiştiğinde diğer cihazlardaki oturumlarınız kapatılır.
            </p>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Parolayı güncelle</button>
            </div>
        </form>
    </div>
</section>