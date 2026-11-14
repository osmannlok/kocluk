<?php
/**
 * admin/login.php
 * Bahar Yöresel - Admin Giriş Sayfası
 */

require_once __DIR__ . '/../config/session.php';

// Zaten giriş yapılmışsa doğrudan dashboard'a yönlendir
if (isAdminLoggedIn()) {
    header('Location: /admin/index.php');
    exit;
}

// Hata mesajını session'dan al ve temizle (flash message mantığı)
$hataMesaji = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Giriş | Bahar Yöresel</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #212529 0%, #495057 100%);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
        }
        .login-icon {
            width: 70px;
            height: 70px;
            background-color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -55px auto 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-4 p-md-5">

            <div class="login-icon">
                <i class="bi bi-flower1 fs-2 text-success"></i>
            </div>

            <h3 class="text-center fw-bold mb-1">Bahar Yöresel</h3>
            <p class="text-center text-secondary mb-4">Admin Paneli Girişi</p>

            <?php if (!empty($hataMesaji)): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?php echo htmlspecialchars($hataMesaji, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="/admin/auth.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">Kullanıcı Adı</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="Kullanıcı adınız" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Şifre</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Şifreniz" required>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Giriş Yap
                    </button>
                </div>
            </form>

        </div>
    </div>

    <p class="text-center text-white-50 small mt-3">
        &copy; <?php echo date('Y'); ?> Bahar Yöresel. Tüm hakları saklıdır.
    </p>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>