<?php
/**
 * @var array $featuredGames
 * @var array $categories
 * @var string $title
 * @var string $description
 */

use App\Services\AdSlot;

$homeTopAd = AdSlot::render('home_top');

ob_start();
?>
<?php if ($homeTopAd !== ''): ?>
    <div class="ad-slot ad-slot-home-top container">
        <?= $homeTopAd ?>
    </div>
<?php endif; ?>

<section class="hero">
    <div class="container">
        <h1>Çocuklar İçin Eğlenceli ve Öğretici Oyunlar</h1>
        <p>Matematik, kelime, mantık, hafıza ve dikkat oyunlarıyla öğrenmeyi keşfedin.</p>
        <a href="/oyunlar" class="btn btn-primary btn-lg">Oyunları Keşfet</a>
    </div>
</section>

<section class="categories-section">
    <div class="container">
        <h2>Kategoriler</h2>
        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <a href="/oyunlar?kategori=<?= urlencode($category['slug']) ?>" class="category-card">
                    <span><?= htmlspecialchars($category['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="featured-games">
    <div class="container">
        <h2>Öne Çıkan Oyunlar</h2>
        <div class="game-grid">
            <?php foreach ($featuredGames as $game): ?>
                <a href="/oyunlar/<?= htmlspecialchars($game['slug']) ?>" class="game-card">
                    <img src="<?= htmlspecialchars($game['cover_image']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" loading="lazy" width="300" height="200">
                    <div class="game-card-body">
                        <span class="game-category"><?= htmlspecialchars($game['category_name']) ?></span>
                        <h3><?= htmlspecialchars($game['title']) ?></h3>
                        <?php if ((int) $game['is_premium'] === 1): ?>
                            <span class="badge badge-premium">Premium</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';