<?php
/**
 * @var string $title
 * @var string $description
 * @var string|null $error
 */
ob_start();
?>
<section class="auth-page">
    <div class="container auth-container">
        <h1>Kayıt Ol</h1>

        <?php if (!empty($error)): ?>
            <p class="alert alert-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="/kayit" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="name">Ad Soyad</label>
                <input type="text" id="name" name="name" required autocomplete="name">
            </div>

            <div class="form-group">
                <label for="email">E-posta</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="password_confirm">Şifre (Tekrar)</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Kayıt Ol</button>
        </form>

        <p class="auth-alt-link">Zaten hesabın var mı? <a href="/giris">Giriş Yap</a></p>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';