<?php
declare(strict_types=1);

// resources/views/partials/breadcrumb.php
// Girdi: $crumbs [[label, url|null], ...]. Yalnızca mikrodata (itemscope/itemprop); JSON-LD satır içi script YOK (CSP).

$crumbs = isset($crumbs) && is_array($crumbs) ? array_values($crumbs) : [];
if ($crumbs === []) {
    return;
}
$total = count($crumbs);
?>
<nav class="ym-breadcrumb" aria-label="Sayfa yolu">
    <ol class="breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
<?php foreach ($crumbs as $i => $crumb):
    $label = (string) ($crumb[0] ?? '');
    $target = $crumb[1] ?? null;
    $isLast = $i === $total - 1;
    ?>
        <li class="breadcrumb-item<?= $isLast ? ' active' : '' ?>" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"<?= $isLast ? ' aria-current="page"' : '' ?>>
<?php if ($target !== null && $target !== '' && !$isLast): ?>
            <a itemprop="item" href="<?= e(str_starts_with((string) $target, '/') ? url((string) $target) : (string) $target) ?>"><span itemprop="name"><?= e($label) ?></span></a>
<?php else: ?>
            <span itemprop="name"><?= e($label) ?></span>
<?php endif; ?>
            <meta itemprop="position" content="<?= e((string) ($i + 1)) ?>">
        </li>
<?php endforeach; ?>
    </ol>
</nav>
