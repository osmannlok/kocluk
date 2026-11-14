<?php
declare(strict_types=1);

// resources/views/front/page.php
// CMS sayfası. Girdi: $page {title, content, updated_at, ...}. Başlık/meta alanları layout'a controller'dan geçer.

use App\Core\View;

$page = isset($page) && is_array($page) ? $page : [];
$updated = !empty($page['updated_at']) ? strtotime((string) $page['updated_at']) : false;
?>
<div class="container">
<?= View::partial('partials.breadcrumb', ['crumbs' => [['Ana Sayfa', '/'], [(string) ($page['title'] ?? ''), null]]]) ?>

    <article class="ym-panel mb-4">
        <h1 class="h2 mb-3"><?= e((string) ($page['title'] ?? '')) ?></h1>
<?php if ($updated !== false): ?>
        <p class="text-muted small">Son güncelleme: <?= e(date('d.m.Y', $updated)) ?></p>
<?php endif; ?>
        <div class="ym-prose">
<?php // İçerik DB'de HTMLPurifier ile temizlenmiş HTML'dir; ham basılan TEK alan budur. ?>
<?= (string) ($page['content'] ?? '') ?>
        </div>
    </article>
</div>
