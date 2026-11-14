<?php
// resources/views/front/auth/login.php
$fieldError = static function (string $key): ?string {
    $value = errors($key);
    if (is_array($value)) {
        $value = reset($value);
    }

    return is_string($value) && $value !== '' ? $value : null;
};
$emailError = $fieldError('email');
$passwordError = $fieldError('password');
?>
<section class="container py-4 py-md-5">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">Giriş Yap</h1>
          <form method="post" action="<?= e(url('/giris')) ?>" data-auth-form>
            <?= csrf_field() ?>
            <div class="mb-3">
              <label for="loginEmail" class="form-label">E-posta</label>
              <input type="email" class="form-control<?= $emailError ? ' is-invalid' : '' ?>" id="loginEmail" name="email"
                     value="<?= e(old('email', '')) ?>" autocomplete="username" inputmode="email" maxlength="190" required
                     <?= $emailError ? 'aria-invalid="true" aria-describedby="loginEmailError"' : '' ?>>
              <?php if ($emailError): ?>
                <div class="invalid-feedback" id="loginEmailError"><?= e($emailError) ?></div>
              <?php endif; ?>
            </div>
            <div class="mb-3">
              <label for="loginPassword" class="form-label">Parola</label>
              <div class="input-group">
                <input type="password" class="form-control<?= $passwordError ? ' is-invalid' : '' ?>" id="loginPassword" name="password"
                       autocomplete="current-password" maxlength="200" required
                       <?= $passwordError ? 'aria-invalid="true" aria-describedby="loginPasswordError"' : '' ?>>
                <button type="button" class="btn btn-outline-secondary" data-password-toggle="#loginPassword"
                        aria-label="Parolayı göster" aria-pressed="false">Göster</button>
              </div>
              <?php if ($passwordError): ?>
                <div class="invalid-feedback d-block" id="loginPasswordError"><?= e($passwordError) ?></div>
              <?php endif; ?>
            </div>
            <div class="d-flex justify-content-end mb-3">
              <a href="<?= e(url('/sifremi-unuttum')) ?>" class="small">Şifremi unuttum</a>
            </div>
            <button type="submit" class="btn btn-primary w-100" data-loading-text="Giriş yapılıyor...">Giriş Yap</button>
          </form>
          <p class="text-center small mt-4 mb-0">
            Hesabınız yok mu? <a href="<?= e(url('/kayit')) ?>">Üye olun</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>