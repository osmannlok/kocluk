<?php
/**
 * @var array $scores
 * @var string $title
 * @var string $description
 */
ob_start();
?>
<section class="profile-page">
    <div class="container">
        <nav class="profile-tabs">
            <a href="/favorilerim" class="profile-tab">Favorilerim</a>
            <a href="/skorlarim" class="profile-tab active">Skorlarım</a>
            <a href="/rozetlerim" class="profile-tab">Rozetlerim</a>
        </nav>

        <h1>Skorlarım</h1>

        <?php if (empty($scores)): ?>
            <p class="empty-state">Henüz bir skorunuz yok. <a href="/oyunlar">Oyun oynayarak başlayın</a>.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Oyun</th>
                        <th>Skor</th>
                        <th>Tarih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scores as $score): ?>
                        <tr>
                            <td>
                                <a href="/oyunlar/<?= htmlspecialchars($score['slug']) ?>"><?= htmlspecialchars($score['title']) ?></a>
                            </td>
                            <td><?= (int) $score['score'] ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($score['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';