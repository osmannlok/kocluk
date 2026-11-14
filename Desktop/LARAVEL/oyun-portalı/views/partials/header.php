<?php
$currentUser = $_SESSION['user'] ?? null;
?>
<header class="site-header">
    <div class="container header-inner">
        <a href="/" class="logo">Eğitim Oyun Portalı</a>

        <nav class="main-nav">
            <a href="/">Ana Sayfa</a>
            <a href="/oyunlar">Oyunlar</a>
            <a href="/blog">Blog</a>
            <a href="/sayfa/iletisim">İletişim</a>
            <?php if ($currentUser): ?>
                <a href="/favorilerim">Favorilerim</a>
                <a href="/skorlarim">Skorlarım</a>
                <a href="/rozetlerim">Rozetlerim</a>
                <a href="/uyelik">Premium</a>
            <?php endif; ?>
        </nav>

        <div class="header-actions">
            <?php if ($currentUser): ?>
                <span class="user-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                <a href="/cikis" class="btn btn-outline">Çıkış</a>
            <?php else: ?>
                <a href="/giris" class="btn btn-outline">Giriş</a>
                <a href="/kayit" class="btn btn-primary">Kayıt Ol</a>
            <?php endif; ?>
        </div>

        <button class="mobile-menu-toggle" aria-label="Menüyü Aç">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>