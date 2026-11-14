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
        <h1>Giriş Yap</h1>

        <?php if (!empty($error)): ?>
            <p class="alert alert-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="/giris" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="email">E-posta</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
        </form>

        <p class="auth-alt-link">Hesabın yok mu? <a href="/kayit">Kayıt Ol</a></p>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';