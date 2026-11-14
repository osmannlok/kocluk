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
?>
<section class="admin-table-section">
    <div class="admin-table-header">
        <p><?= count($games) ?> oyun gösteriliyor</p>
    </div>

    <?php if (empty($games)): ?>
        <p class="empty-state">Henüz oyun bulunmuyor.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>Kategori</th>
                    <th>Yaş Grubu</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($games as $game): ?>
                    <tr>
                        <td><?= (int) $game['id'] ?></td>
                        <td><?= htmlspecialchars($game['title']) ?></td>
                        <td><?= htmlspecialchars($game['category_name']) ?></td>
                        <td><?= htmlspecialchars($game['age_group']) ?></td>
                        <td>
                            <span class="status-badge status-active">Aktif</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Sayfalama">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="/admin/oyunlar?sayfa=<?= $i ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/admin/layouts/main.php';