<?php
/**
 * @var array $post
 * @var array $relatedPosts
 * @var string $title
 * @var string $description
 */

use App\Services\AdSlot;

$inContentAd = AdSlot::render('blog_in_content');

ob_start();
?>
<section class="blog-detail">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/">Ana Sayfa</a> /
            <a href="/blog">Blog</a>
        </nav>

        <article class="blog-article">
            <h1><?= htmlspecialchars($post['title']) ?></h1>
            <p class="blog-date"><?= htmlspecialchars(date('d.m.Y', strtotime($post['published_at']))) ?></p>

            <?php if (!empty($post['cover_image'])): ?>
                <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" width="800" height="450">
            <?php endif; ?>

            <div class="blog-content">
                <?= nl2br(htmlspecialchars($post['content'])) ?>
            </div>

            <?php if ($inContentAd !== ''): ?>
                <div class="ad-slot ad-slot-blog-in-content">
                    <?= $inContentAd ?>
                </div>
            <?php endif; ?>
        </article>

        <?php if (!empty($relatedPosts)): ?>
            <div class="related-posts">
                <h2>Benzer Yazılar</h2>
                <div class="blog-grid">
                    <?php foreach ($relatedPosts as $related): ?>
                        <a href="/blog/<?= htmlspecialchars($related['slug']) ?>" class="blog-card">
                            <?php if (!empty($related['cover_image'])): ?>
                                <img src="<?= htmlspecialchars($related['cover_image']) ?>" alt="<?= htmlspecialchars($related['title']) ?>" loading="lazy" width="360" height="220">
                            <?php endif; ?>
                            <div class="blog-card-body">
                                <h3><?= htmlspecialchars($related['title']) ?></h3>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';