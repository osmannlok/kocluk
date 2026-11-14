<?php
// resources/views/front/auth/verify-notice.php
$email = isset($email) && is_string($email) ? $email : null;
$verified = !empty($verified);
$canResend = !empty($canResend);
?>
<section class="container py-4 py-md-5">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body p-4 text-center">
          <h1 class="h4 mb-3">E-postanızı Doğrulayın</h1>
          <?php if ($verified): ?>
            <p class="mb-3">E-posta adresiniz zaten doğrulanmış.</p>
            <a href="<?= e(url('/hesabim')) ?>" class="btn btn-primary w-100">Hesabıma Git</a>
          <?php else: ?>
            <p class="mb-3">
              <?php if ($email !== null): ?>
                <strong><?= e($email) ?></strong> adresine bir doğrulama bağlantısı gönderdik.
              <?php else: ?>
                E-posta adresinize bir doğrulama bağlantısı gönderdik.
              <?php endif; ?>
              Bağlantıya tıklayarak hesabınızı etkinleştirin. E-posta görünmüyorsa gereksiz (spam) klasörünü de kontrol edin.
            </p>
            <?php if ($canResend): ?>
              <form method="post" action="<?= e(url('/eposta-dogrula/tekrar')) ?>" data-auth-form>
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-primary w-100" data-loading-text="Gönderiliyor...">Doğrulama E-postasını Tekrar Gönder</button>
              </form>
            <?php else: ?>
              <p class="small text-body-secondary mb-0">
                Doğrulama e-postası gelmediyse <a href="<?= e(url('/sifremi-unuttum')) ?>">Şifremi unuttum</a> adımıyla da hesabınızı etkinleştirebilirsiniz.
              </p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>