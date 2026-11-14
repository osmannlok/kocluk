<?php
/**
 * @var array $page
 * @var string $title
 * @var string $description
 */
ob_start();
?>
<section class="static-page">
    <div class="container">
        <h1><?= htmlspecialchars($page['title']) ?></h1>
        <div class="static-page-content">
            <?= nl2br(htmlspecialchars($page['content'])) ?>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';