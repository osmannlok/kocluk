<?php
/**
 * @var string $title
 * @var string $description
 * @var string $content
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Admin Panel') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-logo">Admin Panel</div>
            <nav class="admin-nav">
                <a href="/admin">Panel</a>
                <a href="/admin/oyunlar">Oyunlar</a>
                <a href="/admin/blog">Blog</a>
                <a href="/admin/kategoriler">Kategoriler</a>
                <a href="/admin/kullanicilar">Kullanıcılar</a>
                <a href="/" target="_blank" rel="noopener">Siteyi Görüntüle</a>
                <a href="/cikis">Çıkış</a>
            </nav>
        </aside>

        <div class="admin-content">
            <header class="admin-topbar">
                <h1><?= htmlspecialchars($title ?? '') ?></h1>
            </header>

            <main class="admin-main">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
</body>
</html>