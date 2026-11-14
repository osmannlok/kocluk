<?php
/**
 * resources/views/layouts/admin.php
 * Yönetim iskeleti: sabit kenar çubuğu (mobilde offcanvas), üst çubuk, flash, içerik.
 * Menü öğeleri can() ile filtrelenir; yetkisiz bölüm hiç çizilmez.
 * Beklenen değişkenler: $content, $title (ops.), $pageScripts (ops., asset yolları).
 */

$currentUser = auth() ?? [];
$fullName = trim((string) ($currentUser['first_name'] ?? '') . ' ' . (string) ($currentUser['last_name'] ?? ''));
$roleName = (string) ($currentUser['role_name'] ?? '');
$pageTitle = isset($title) && is_string($title) && $title !== '' ? $title : 'Yönetim';
$siteName = (string) config('app.name', 'Yöresel Market');
$pageScripts = isset($pageScripts) && is_array($pageScripts) ? $pageScripts : [];

// [etiket, ikon, yol, permission, eşleşme deseni]
$navGroups = [
    'Genel' => [
        ['Dashboard', 'bi-speedometer2', '/yonetim', 'dashboard.view', '/yonetim'],
    ],
    'Satış' => [
        ['Siparişler', 'bi-bag-check', '/yonetim/siparisler', 'order.view', '/yonetim/siparisler*'],
        ['Müşteriler', 'bi-people', '/yonetim/musteriler', 'customer.view', '/yonetim/musteriler*'],
        ['Kuponlar', 'bi-ticket-perforated', '/yonetim/kuponlar', 'coupon.manage', '/yonetim/kuponlar*'],
    ],
    'Katalog' => [
        ['Ürünler', 'bi-box-seam', '/yonetim/urunler', 'product.view', '/yonetim/urunler*'],
        ['Kategoriler', 'bi-diagram-3', '/yonetim/kategoriler', 'category.manage', '/yonetim/kategoriler*'],
        ['Stok', 'bi-boxes', '/yonetim/stok', 'stock.manage', '/yonetim/stok*'],
    ],
    'İçerik' => [
        ['Banner', 'bi-images', '/yonetim/bannerlar', 'banner.manage', '/yonetim/bannerlar*'],
        ['Menüler', 'bi-list-nested', '/yonetim/menuler', 'menu.manage', '/yonetim/menuler*'],
        ['Sayfalar', 'bi-file-earmark-text', '/yonetim/sayfalar', 'page.manage', '/yonetim/sayfalar*'],
        // Duyurular için ayrı permission yok; pazarlama içeriği olarak banner.manage ile korunur.
        ['Duyurular', 'bi-megaphone', '/yonetim/duyurular', 'banner.manage', '/yonetim/duyurular*'],
        // Mesajlar için ayrı permission yok; müşteri iletişimi olarak customer.view ile korunur.
        ['Mesajlar', 'bi-envelope', '/yonetim/mesajlar', 'customer.view', '/yonetim/mesajlar*'],
    ],
    'Yönetim' => [
        ['Kullanıcılar', 'bi-person-gear', '/yonetim/kullanicilar', 'user.manage', '/yonetim/kullanicilar*'],
        ['Roller', 'bi-shield-lock', '/yonetim/roller', 'role.manage', '/yonetim/roller*'],
        ['Denetim', 'bi-journal-text', '/yonetim/denetim', 'audit.view', '/yonetim/denetim*'],
        ['Ayarlar', 'bi-gear', '/yonetim/ayarlar', 'setting.manage', '/yonetim/ayarlar*'],
    ],
];

$alertMap = ['success' => 'success', 'error' => 'danger', 'danger' => 'danger', 'warning' => 'warning', 'info' => 'info'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · Yönetim · <?= e($siteName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('/assets/vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
<a class="admin-skip" href="#adminMain">İçeriğe geç</a>

<header class="navbar fixed-top admin-topbar">
    <div class="container-fluid flex-nowrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-light d-lg-none"
                    data-bs-toggle="offcanvas" data-bs-target="#adminSidebar"
                    aria-controls="adminSidebar" aria-label="Menüyü aç">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-light d-none d-lg-inline-flex"
                    data-admin-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true"
                    aria-label="Kenar çubuğunu aç/kapat">
                <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
            </button>
            <a class="navbar-brand admin-brand mb-0" href="<?= e(url('/yonetim')) ?>"><?= e($siteName) ?> <span class="admin-brand-tag">Yönetim</span></a>
        </div>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <span class="admin-user d-none d-md-inline">
                <i class="bi bi-person-circle" aria-hidden="true"></i>
                <?= e($fullName !== '' ? $fullName : 'Kullanıcı') ?>
                <?php if ($roleName !== ''): ?><span class="admin-role"><?= e($roleName) ?></span><?php endif; ?>
            </span>
            <a class="btn btn-sm btn-outline-light" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Siteye git</span>
            </a>
            <form method="post" action="<?= e(url('/cikis')) ?>" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-light">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Çıkış</span>
                </button>
            </form>
        </div>
    </div>
</header>

<div class="offcanvas-lg offcanvas-start admin-sidebar" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header border-bottom border-secondary">
        <h2 class="offcanvas-title h6 mb-0" id="adminSidebarLabel">Menü</h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="admin-nav w-100" aria-label="Yönetim menüsü">
            <?php foreach ($navGroups as $groupLabel => $items): ?>
                <?php
                // Yetkisiz öğeler elenir; hiç öğesi kalmayan bölüm başlığı da çizilmez.
                $visible = array_values(array_filter($items, static fn (array $i): bool => can($i[3])));
                if ($visible === []) {
                    continue;
                }
                ?>
                <p class="admin-nav-title"><?= e($groupLabel) ?></p>
                <ul class="list-unstyled mb-2">
                    <?php foreach ($visible as $item): ?>
                        <?php $active = is_path($item[4]); ?>
                        <li>
                            <a class="admin-nav-link<?= $active ? ' active' : '' ?>"
                               href="<?= e(url($item[2])) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i class="bi <?= e($item[1]) ?>" aria-hidden="true"></i>
                                <span><?= e($item[0]) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<main id="adminMain" class="admin-main" tabindex="-1">
    <div class="admin-content">
        <?php foreach (flash_messages() as $flashKey => $flashItem): ?>
            <?php
            if (is_array($flashItem)) {
                $flashType = (string) ($flashItem['type'] ?? 'info');
                $flashText = (string) ($flashItem['message'] ?? '');
            } else {
                $flashType = is_string($flashKey) ? $flashKey : 'info';
                $flashText = (string) $flashItem;
            }
            if ($flashText === '') {
                continue;
            }
            $flashClass = $alertMap[$flashType] ?? 'info';
            ?>
            <div class="alert alert-<?= e($flashClass) ?> alert-dismissible fade show" role="alert">
                <?= e($flashText) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </div>
    <footer class="admin-footer">&copy; <?= e(date('Y')) ?> <?= e($siteName) ?> · Yönetim Paneli</footer>
</main>

<div class="toast-container position-fixed top-0 end-0 p-3" id="adminToastArea" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e(asset('/assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('/assets/js/admin.js')) ?>" defer></script>
<?php foreach ($pageScripts as $scriptPath): ?>
    <script src="<?= e(asset((string) $scriptPath)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>