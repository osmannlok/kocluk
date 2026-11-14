<?php
/**
 * @var array $badges
 * @var string $title
 * @var string $description
 */
ob_start();
?>
<section class="profile-page">
    <div class="container">
        <nav class="profile-tabs">
            <a href="/favorilerim" class="profile-tab">Favorilerim</a>
            <a href="/skorlarim" class="profile-tab">Skorlarım</a>
            <a href="/rozetlerim" class="profile-tab active">Rozetlerim</a>
        </nav>

        <h1>Rozetlerim</h1>

        <?php if (empty($badges)): ?>
            <p class="empty-state">Henüz bir rozet kazanmadınız. Oyun oynamaya devam edin!</p>
        <?php else: ?>
            <div class="badge-grid">
                <?php foreach ($badges as $badge): ?>
                    <div class="badge-card">
                        <img src="<?= htmlspecialchars($badge['icon']) ?>" alt="<?= htmlspecialchars($badge['name']) ?>" loading="lazy" width="64" height="64">
                        <h3><?= htmlspecialchars($badge['name']) ?></h3>
                        <?php if (!empty($badge['description'])): ?>
                            <p><?= htmlspecialchars($badge['description']) ?></p>
                        <?php endif; ?>
                        <span class="badge-date">Kazanıldı: <?= htmlspecialchars(date('d.m.Y', strtotime($badge['earned_at']))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';