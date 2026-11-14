<?php
/**
 * @var array $favorites
 * @var string $title
 * @var string $description
 */
ob_start();
?>
<section class="profile-page">
    <div class="container">
        <nav class="profile-tabs">
            <a href="/favorilerim" class="profile-tab active">Favorilerim</a>
            <a href="/skorlarim" class="profile-tab">Skorlarım</a>
            <a href="/rozetlerim" class="profile-tab">Rozetlerim</a>
        </nav>

        <h1>Favorilerim</h1>

        <?php if (empty($favorites)): ?>
            <p class="empty-state">Henüz favori oyununuz yok. <a href="/oyunlar">Oyunları keşfedin</a>.</p>
        <?php else: ?>
            <div class="game-grid">
                <?php foreach ($favorites as $game): ?>
                    <a href="/oyunlar/<?= htmlspecialchars($game['slug']) ?>" class="game-card">
                        <img src="<?= htmlspecialchars($game['cover_image']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" loading="lazy" width="300" height="200">
                        <div class="game-card-body">
                            <span class="game-category"><?= htmlspecialchars($game['category_name']) ?></span>
                            <h3><?= htmlspecialchars($game['title']) ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';