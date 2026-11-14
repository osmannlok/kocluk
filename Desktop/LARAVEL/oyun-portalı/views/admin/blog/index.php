<?php
/**
 * @var array $posts
 * @var int $totalPages
 * @var string $title
 * @var string $description
 */
ob_start();

$currentPage = isset($_GET['sayfa']) ? max(1, (int) $_GET['sayfa']) : 1;
?>
<section class="admin-table-section">
    <div class="admin-table-header">
        <p><?= count($posts) ?> yazı gösteriliyor</p>
    </div>

    <?php if (empty($posts)): ?>
        <p class="empty-state">Henüz blog yazısı bulunmuyor.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>Yayın Tarihi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td><?= (int) $post['id'] ?></td>
                        <td><?= htmlspecialchars($post['title']) ?></td>
                        <td><?= htmlspecialchars(date('d.m.Y', strtotime($post['published_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Sayfalama">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="/admin/blog?sayfa=<?= $i ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>">
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