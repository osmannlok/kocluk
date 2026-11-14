<?php
// resources/views/front/auth/reset.php
$fieldError = static function (string $key): ?string {
    $value = errors($key);
    if (is_array($value)) {
        $value = reset($value);
    }

    return is_string($value) && $value !== '' ? $value : null;
};
$passwordError = $fieldError('password');
?>
<section class="container py-4 py-md-5">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">Yeni Parola Belirle</h1>
          <form method="post" action="<?= e(url('/sifre-sifirla')) ?>" data-auth-form>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
              <label for="resetPassword" class="form-label">Yeni parola</label>
              <div class="input-group">
                <input type="password" class="form-control<?= $passwordError ? ' is-invalid' : '' ?>" id="resetPassword" name="password"
                       autocomplete="new-password" minlength="8" maxlength="200" required aria-describedby="resetStrength">
                <button type="button" class="btn btn-outline-secondary" data-password-toggle="#resetPassword"
                        aria-label="Parolayı göster" aria-pressed="false">Göster</button>
              </div>
              <?php if ($passwordError): ?><div class="invalid-feedback d-block"><?= e($passwordError) ?></div><?php endif; ?>
              <div class="mt-2" id="resetStrength" data-strength-meter data-target="#resetPassword">
                <div class="progress" role="progressbar" aria-label="Parola gücü" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-strength-progress>
                  <div class="progress-bar" data-strength-bar data-width="0"></div>
                </div>
                <p class="small mt-1 mb-1" aria-live="polite" data-strength-label></p>
                <ul class="list-unstyled small mb-0">
                  <li class="text-body-secondary" data-rule="length">En az 8 karakter</li>
                  <li class="text-body-secondary" data-rule="mix">Yalnızca rakam veya yalnızca harf olmasın</li>
                </ul>
              </div>
            </div>
            <div class="mb-3">
              <label for="resetPasswordConfirm" class="form-label">Yeni parola (tekrar)</label>
              <input type="password" class="form-control" id="resetPasswordConfirm" name="password_confirmation"
                     autocomplete="new-password" maxlength="200" required
                     data-match-with="#resetPassword" data-match-message="resetMatchMessage">
              <div class="small text-danger" id="resetMatchMessage" aria-live="polite"></div>
            </div>
            <button type="submit" class="btn btn-primary w-100" data-loading-text="Kaydediliyor...">Parolayı Güncelle</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>