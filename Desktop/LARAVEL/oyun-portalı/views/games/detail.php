<?php
/**
 * @var array $game
 * @var array $relatedGames
 * @var string $title
 * @var string $description
 */

use App\Models\Favorite;
use App\Services\AdSlot;

$currentUser = $_SESSION['user'] ?? null;
$isFavorite = false;

if ($currentUser) {
    $favoriteModel = new Favorite();
    $isFavorite = $favoriteModel->isFavorite((int) $currentUser['id'], (int) $game['id']);
}

$sidebarAd = AdSlot::render('game_sidebar');

ob_start();
?>
<section class="game-detail">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/">Ana Sayfa</a> /
            <a href="/oyunlar">Oyunlar</a> /
            <a href="/oyunlar?kategori=<?= urlencode($game['category_slug']) ?>"><?= htmlspecialchars($game['category_name']) ?></a>
        </nav>

        <div class="game-detail-layout">
            <div class="game-detail-main">
                <div class="game-detail-header">
                    <img src="<?= htmlspecialchars($game['cover_image']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" width="400" height="260">
                    <div class="game-detail-info">
                        <h1><?= htmlspecialchars($game['title']) ?></h1>
                        <p class="game-meta">
                            <span class="badge">Yaş: <?= htmlspecialchars($game['age_group']) ?></span>
                            <span class="badge"><?= htmlspecialchars($game['category_name']) ?></span>
                            <?php if ((int) $game['is_premium'] === 1): ?>
                                <span class="badge badge-premium">Premium</span>
                            <?php endif; ?>
                        </p>
                        <p class="game-description"><?= htmlspecialchars($game['description']) ?></p>
                        <?php if (!empty($game['target_skill'])): ?>
                            <p class="game-skill"><strong>Kazanım:</strong> <?= htmlspecialchars($game['target_skill']) ?></p>
                        <?php endif; ?>

                        <div class="game-actions">
                            <a href="#oyna" class="btn btn-primary btn-lg">Oyna</a>

                            <?php if ($currentUser): ?>
                                <?php if ($isFavorite): ?>
                                    <form method="POST" action="/oyunlar/<?= htmlspecialchars($game['slug']) ?>/favori-sil" class="favorite-form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <button type="submit" class="btn btn-outline">Favorilerden Çıkar</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="/oyunlar/<?= htmlspecialchars($game['slug']) ?>/favori-ekle" class="favorite-form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <button type="submit" class="btn btn-outline">Favorilere Ekle</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="/giris" class="btn btn-outline">Favorilere eklemek için giriş yap</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="oyna" class="game-play-area">
                    <p>Oyun burada yüklenecek.</p>

                    <?php if ($currentUser): ?>
                        <form method="POST" action="/oyunlar/<?= htmlspecialchars($game['slug']) ?>/skor-kaydet" class="score-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="score" value="0" id="score-input">
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (!empty($relatedGames)): ?>
                    <div class="related-games">
                        <h2>Benzer Oyunlar</h2>
                        <div class="game-grid">
                            <?php foreach ($relatedGames as $related): ?>
                                <a href="/oyunlar/<?= htmlspecialchars($related['slug']) ?>" class="game-card">
                                    <img src="<?= htmlspecialchars($related['cover_image']) ?>" alt="<?= htmlspecialchars($related['title']) ?>" loading="lazy" width="300" height="200">
                                    <div class="game-card-body">
                                        <h3><?= htmlspecialchars($related['title']) ?></h3>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($sidebarAd !== ''): ?>
                <aside class="game-detail-sidebar">
                    <div class="ad-slot ad-slot-game-sidebar">
                        <?= $sidebarAd ?>
                    </div>
                </aside>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';