<?php
/**
 * @var array $games
 * @var array $categories
 * @var int $totalPages
 * @var string $title
 * @var string $description
 */
ob_start();

$currentPage = isset($_GET['sayfa']) ? max(1, (int) $_GET['sayfa']) : 1;
$currentCategory = $_GET['kategori'] ?? '';
?>
<section class="games-page">
    <div class="container">
        <h1>Oyunlar</h1>
        <p class="page-intro">Matematik, kelime, mantık, hafıza ve dikkat becerilerini geliştiren eğitici oyunları keşfedin.</p>

        <div class="filter-bar">
            <a href="/oyunlar" class="filter-chip <?= $currentCategory === '' ? 'active' : '' ?>">Tümü</a>
            <?php foreach ($categories as $category): ?>
                <a href="/oyunlar?kategori=<?= urlencode($category['slug']) ?>"
                   class="filter-chip <?= $currentCategory === $category['slug'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($category['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($games)): ?>
            <p class="empty-state">Bu kategoride henüz oyun bulunmuyor.</p>
        <?php else: ?>
            <div class="game-grid">
                <?php foreach ($games as $game): ?>
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

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Sayfalama">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="/oyunlar?sayfa=<?= $i ?><?= $currentCategory !== '' ? '&kategori=' . urlencode($currentCategory) : '' ?>"
                           class="page-link <?= $i === $currentPage ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';