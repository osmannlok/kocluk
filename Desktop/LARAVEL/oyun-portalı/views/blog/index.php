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
<section class="blog-page">
    <div class="container">
        <h1>Blog</h1>
        <p class="page-intro">Eğitim teknolojisi, oyunlaştırma, ebeveyn rehberleri ve öğretmen notları üzerine yazılar.</p>

        <?php if (empty($posts)): ?>
            <p class="empty-state">Henüz yayınlanmış yazı bulunmuyor.</p>
        <?php else: ?>
            <div class="blog-grid">
                <?php foreach ($posts as $post): ?>
                    <a href="/blog/<?= htmlspecialchars($post['slug']) ?>" class="blog-card">
                        <?php if (!empty($post['cover_image'])): ?>
                            <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy" width="360" height="220">
                        <?php endif; ?>
                        <div class="blog-card-body">
                            <span class="blog-date"><?= htmlspecialchars(date('d.m.Y', strtotime($post['published_at']))) ?></span>
                            <h3><?= htmlspecialchars($post['title']) ?></h3>
                            <?php if (!empty($post['excerpt'])): ?>
                                <p><?= htmlspecialchars($post['excerpt']) ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Sayfalama">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="/blog?sayfa=<?= $i ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>">
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