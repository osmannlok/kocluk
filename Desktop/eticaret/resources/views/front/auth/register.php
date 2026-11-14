<?php
// resources/views/front/auth/register.php
$fieldError = static function (string $key): ?string {
    $value = errors($key);
    if (is_array($value)) {
        $value = reset($value);
    }

    return is_string($value) && $value !== '' ? $value : null;
};
$firstNameError = $fieldError('first_name');
$lastNameError = $fieldError('last_name');
$emailError = $fieldError('email');
$phoneError = $fieldError('phone');
$passwordError = $fieldError('password');
$kvkkError = $fieldError('kvkk');
$termsError = $fieldError('terms');
$legalLinks = is_array($legalLinks ?? null) ? $legalLinks : ['kvkk' => '/sayfa/kvkk', 'terms' => '/sayfa/mesafeli-satis-sozlesmesi'];
?>
<section class="container py-4 py-md-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">Üye Ol</h1>
          <form method="post" action="<?= e(url('/kayit')) ?>" data-auth-form>
            <?= csrf_field() ?>
            <div class="visually-hidden" aria-hidden="true">
              <label for="regWebsite">Web sitesi</label>
              <input type="text" id="regWebsite" name="website" tabindex="-1" autocomplete="off">
            </div>
            <div class="row g-3">
              <div class="col-12 col-sm-6">
                <label for="regFirstName" class="form-label">Ad</label>
                <input type="text" class="form-control<?= $firstNameError ? ' is-invalid' : '' ?>" id="regFirstName" name="first_name"
                       value="<?= e(old('first_name', '')) ?>" autocomplete="given-name" maxlength="80" required>
                <?php if ($firstNameError): ?><div class="invalid-feedback"><?= e($firstNameError) ?></div><?php endif; ?>
              </div>
              <div class="col-12 col-sm-6">
                <label for="regLastName" class="form-label">Soyad</label>
                <input type="text" class="form-control<?= $lastNameError ? ' is-invalid' : '' ?>" id="regLastName" name="last_name"
                       value="<?= e(old('last_name', '')) ?>" autocomplete="family-name" maxlength="80" required>
                <?php if ($lastNameError): ?><div class="invalid-feedback"><?= e($lastNameError) ?></div><?php endif; ?>
              </div>
              <div class="col-12">
                <label for="regEmail" class="form-label">E-posta</label>
                <input type="email" class="form-control<?= $emailError ? ' is-invalid' : '' ?>" id="regEmail" name="email"
                       value="<?= e(old('email', '')) ?>" autocomplete="email" inputmode="email" maxlength="190" required>
                <?php if ($emailError): ?><div class="invalid-feedback"><?= e($emailError) ?></div><?php endif; ?>
              </div>
              <div class="col-12">
                <label for="regPhone" class="form-label">Telefon <span class="text-body-secondary">(isteğe bağlı)</span></label>
                <input type="tel" class="form-control<?= $phoneError ? ' is-invalid' : '' ?>" id="regPhone" name="phone"
                       value="<?= e(old('phone', '')) ?>" autocomplete="tel" inputmode="tel" maxlength="20" placeholder="05XX XXX XX XX">
                <?php if ($phoneError): ?><div class="invalid-feedback"><?= e($phoneError) ?></div><?php endif; ?>
              </div>
              <div class="col-12">
                <label for="regPassword" class="form-label">Parola</label>
                <div class="input-group">
                  <input type="password" class="form-control<?= $passwordError ? ' is-invalid' : '' ?>" id="regPassword" name="password"
                         autocomplete="new-password" minlength="8" maxlength="200" required aria-describedby="regStrength">
                  <button type="button" class="btn btn-outline-secondary" data-password-toggle="#regPassword"
                          aria-label="Parolayı göster" aria-pressed="false">Göster</button>
                </div>
                <?php if ($passwordError): ?><div class="invalid-feedback d-block"><?= e($passwordError) ?></div><?php endif; ?>
                <div class="mt-2" id="regStrength" data-strength-meter data-target="#regPassword" data-email="#regEmail">
                  <div class="progress" role="progressbar" aria-label="Parola gücü" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-strength-progress>
                    <div class="progress-bar" data-strength-bar data-width="0"></div>
                  </div>
                  <p class="small mt-1 mb-1" aria-live="polite" data-strength-label></p>
                  <ul class="list-unstyled small mb-0">
                    <li class="text-body-secondary" data-rule="length">En az 8 karakter</li>
                    <li class="text-body-secondary" data-rule="mix">Yalnızca rakam veya yalnızca harf olmasın</li>
                    <li class="text-body-secondary" data-rule="email">E-posta adresinizi içermesin</li>
                  </ul>
                </div>
              </div>
              <div class="col-12">
                <label for="regPasswordConfirm" class="form-label">Parola (tekrar)</label>
                <input type="password" class="form-control" id="regPasswordConfirm" name="password_confirmation"
                       autocomplete="new-password" maxlength="200" required
                       data-match-with="#regPassword" data-match-message="regMatchMessage">
                <div class="small text-danger" id="regMatchMessage" aria-live="polite"></div>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input<?= $kvkkError ? ' is-invalid' : '' ?>" type="checkbox" id="regKvkk" name="kvkk" value="1" required>
                  <label class="form-check-label" for="regKvkk">
                    <a href="<?= e(url($legalLinks['kvkk'])) ?>" target="_blank" rel="noopener">KVKK aydınlatma metnini</a> okudum, kabul ediyorum.
                  </label>
                  <?php if ($kvkkError): ?><div class="invalid-feedback d-block"><?= e($kvkkError) ?></div><?php endif; ?>
                </div>
                <div class="form-check mt-2">
                  <input class="form-check-input<?= $termsError ? ' is-invalid' : '' ?>" type="checkbox" id="regTerms" name="terms" value="1" required>
                  <label class="form-check-label" for="regTerms">
                    <a href="<?= e(url($legalLinks['terms'])) ?>" target="_blank" rel="noopener">Üyelik ve mesafeli satış sözleşmesini</a> okudum, kabul ediyorum.
                  </label>
                  <?php if ($termsError): ?><div class="invalid-feedback d-block"><?= e($termsError) ?></div><?php endif; ?>
                </div>
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="regMarketing" name="marketing_consent" value="1">
                  <label class="form-check-label" for="regMarketing">Kampanya ve duyurulardan e-posta ile haberdar olmak istiyorum. (isteğe bağlı)</label>
                </div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary w-100" data-loading-text="Kaydediliyor...">Üye Ol</button>
              </div>
            </div>
          </form>
          <p class="text-center small mt-4 mb-0">
            Zaten hesabınız var mı? <a href="<?= e(url('/giris')) ?>">Giriş yapın</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>