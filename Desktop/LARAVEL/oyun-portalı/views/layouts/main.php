<?php
/**
 * @var string $title
 * @var string $description
 * @var string $content
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Eğitim Oyun Portalı') ?></title>
    <meta name="description" content="<?= htmlspecialchars($description ?? '') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($title ?? '') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description ?? '') ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php require BASE_PATH . '/views/partials/header.php'; ?>

    <main class="site-main">
        <?= $content ?? '' ?>
    </main>

    <?php require BASE_PATH . '/views/partials/footer.php'; ?>

    <script src="/assets/js/main.js" defer></script>
</body>
</html>