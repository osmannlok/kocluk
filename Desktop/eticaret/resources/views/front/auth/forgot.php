<?php
// resources/views/front/auth/forgot.php
$fieldError = static function (string $key): ?string {
    $value = errors($key);
    if (is_array($value)) {
        $value = reset($value);
    }

    return is_string($value) && $value !== '' ? $value : null;
};
$emailError = $fieldError('email');
?>
<section class="container py-4 py-md-5">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-2">Şifremi Unuttum</h1>
          <p class="text-body-secondary small mb-3">E-posta adresinizi girin; hesabınız varsa parola sıfırlama bağlantısı gönderelim.</p>
          <form method="post" action="<?= e(url('/sifremi-unuttum')) ?>" data-auth-form>
            <?= csrf_field() ?>
            <div class="mb-3">
              <label for="forgotEmail" class="form-label">E-posta</label>
              <input type="email" class="form-control<?= $emailError ? ' is-invalid' : '' ?>" id="forgotEmail" name="email"
                     value="<?= e(old('email', '')) ?>" autocomplete="email" inputmode="email" maxlength="190" required>
              <?php if ($emailError): ?><div class="invalid-feedback"><?= e($emailError) ?></div><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary w-100" data-loading-text="Gönderiliyor...">Bağlantı Gönder</button>
          </form>
          <p class="text-center small mt-4 mb-0"><a href="<?= e(url('/giris')) ?>">Giriş sayfasına dön</a></p>
        </div>
      </div>
    </div>
  </div>
</section>