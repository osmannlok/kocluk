<?php
declare(strict_types=1);

// resources/views/partials/header.php
// Sticky başlık: logo, arama (GET /ara), hesap menüsü, sepet düğmesi (mini-cart offcanvas), mega menü + mobil offcanvas.

use App\Models\Menu;
use App\Models\MenuItem;

$settings = isset($settings) && is_array($settings) ? $settings : [];
$siteName = (string) ($settings['site.name'] ?? 'Yöresel Market');
$cartCount = isset($cartCount) ? max(0, (int) $cartCount) : 0;
$user = auth();

$menuModel = new Menu();
$itemModel = new MenuItem();

// Menü konumuna göre ağaç (yalnızca aktif menü ve kalemler; güvensiz URL'ler modelde '#'e çevrilir).
$loadTree = static function (string $location) use ($menuModel, $itemModel): array {
    $menu = $menuModel->findByLocation($location);
    return $menu ? $itemModel->treeByMenu((int) $menu['id']) : [];
};

$headerTree = $loadTree('header');
$mobileTree = $loadTree('mobile');
if ($mobileTree === []) {
    $mobileTree = $headerTree;
}

$href = static function (mixed $u): string {
    $u = (string) $u;
    return str_starts_with($u, '/') ? url($u) : ($u === '' ? '#' : $u);
};
$targetAttrs = static function (array $item): string {
    return !empty($item['open_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
};
?>
<header class="ym-header">
    <div class="container">
        <div class="ym-header__bar">
            <button type="button" class="ym-icon-btn d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#ymMenuOffcanvas" aria-controls="ymMenuOffcanvas" aria-label="Menüyü aç">
                <svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-menu"></use></svg>
            </button>

            <a class="ym-logo-link" href="<?= e(url('/')) ?>" aria-label="<?= e($siteName) ?> ana sayfa">
                <img class="ym-logo" src="<?= e(asset('img/logo.svg')) ?>" alt="<?= e($siteName) ?>" width="160" height="32">
            </a>

            <form id="ymSearch" class="ym-search" action="<?= e(url('/ara')) ?>" method="get" role="search" data-no-lock>
                <label for="ymSearchInput" class="visually-hidden">Ürün ara</label>
                <input id="ymSearchInput" class="form-control ym-search__input" type="search" name="q" maxlength="100" placeholder="Ürün, kategori veya yöre ara" autocomplete="off" enterkeyhint="search">
                <button type="submit" class="btn btn-primary ym-search__btn" aria-label="Ara">
                    <svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-search"></use></svg>
                    <span class="d-none d-xl-inline">Ara</span>
                </button>
            </form>

            <span class="ym-header__spacer"></span>

            <div class="ym-header__actions">
                <button type="button" class="ym-icon-btn d-lg-none" data-search-toggle aria-controls="ymSearch" aria-expanded="false" aria-label="Aramayı aç">
                    <svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-search"></use></svg>
                </button>

                <div class="dropdown d-none d-lg-block">
                    <button type="button" class="ym-icon-btn" data-bs-toggle="dropdown" aria-expanded="false" id="ymAccountBtn">
                        <svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-user"></use></svg>
                        <span class="ym-icon-btn__label"><?= $user ? e((string) ($user['first_name'] ?? 'Hesabım')) : 'Hesabım' ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="ymAccountBtn">
<?php if ($user): ?>
                        <li><h6 class="dropdown-header"><?= e(trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''))) ?></h6></li>
                        <li><a class="dropdown-item" href="<?= e(url('/hesabim')) ?>">Hesabım</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/hesabim/siparisler')) ?>">Siparişlerim</a></li>
<?php if (can('dashboard.view')): ?>
                        <li><a class="dropdown-item" href="<?= e(url('/yonetim')) ?>">Yönetim Paneli</a></li>
<?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= e(url('/cikis')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item">Çıkış</button>
                            </form>
                        </li>
<?php else: ?>
                        <li><a class="dropdown-item" href="<?= e(url('/giris')) ?>">Giriş yap</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('/kayit')) ?>">Kayıt ol</a></li>
<?php endif; ?>
                    </ul>
                </div>

                <button type="button" class="ym-icon-btn" data-bs-toggle="offcanvas" data-bs-target="#ymCartOffcanvas" aria-controls="ymCartOffcanvas" aria-label="Sepet">
                    <svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-cart"></use></svg>
                    <span class="ym-icon-btn__label">Sepet</span>
                    <span class="ym-cart-badge" data-cart-count data-count="<?= e((string) $cartCount) ?>"<?= $cartCount === 0 ? ' hidden' : '' ?>><?= e($cartCount > 99 ? '99+' : (string) $cartCount) ?></span>
                </button>
            </div>
        </div>
    </div>

<?php if ($headerTree !== []): ?>
    <div class="ym-subnav">
        <div class="container">
            <nav class="ym-nav" aria-label="Ana menü">
                <ul class="ym-nav__list">
<?php foreach ($headerTree as $item):
    $children = (array) ($item['children'] ?? []);
    $isMega = !empty($item['is_mega']) && $children !== [];
    $label = (string) ($item['label'] ?? '');
    ?>
                    <li class="ym-nav__item<?= $isMega ? ' ym-nav__item--mega' : '' ?>">
                        <a class="ym-nav__link" href="<?= e($href($item['url'] ?? '#')) ?>"<?= $targetAttrs($item) ?>>
                            <?= e($label) ?>
<?php if (!empty($item['badge_text'])): ?>
                            <span class="ym-badge-menu"><?= e((string) $item['badge_text']) ?></span>
<?php endif; ?>
                        </a>
<?php if ($children !== []): ?>
                        <button type="button" class="ym-nav__toggle" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="<?= e($label) ?> alt menüsünü aç">
                            <svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-chevron-down"></use></svg>
                        </button>
<?php if ($isMega): ?>
                        <div class="dropdown-menu ym-mega">
                            <div class="ym-mega__grid">
<?php foreach ($children as $col): $sub = (array) ($col['children'] ?? []); ?>
                                <div class="ym-mega__col">
                                    <a class="ym-mega__title" href="<?= e($href($col['url'] ?? '#')) ?>"<?= $targetAttrs($col) ?>><?= e((string) ($col['label'] ?? '')) ?></a>
<?php if ($sub !== []): ?>
                                    <ul class="ym-mega__list">
<?php foreach ($sub as $link): ?>
                                        <li><a class="ym-mega__link" href="<?= e($href($link['url'] ?? '#')) ?>"<?= $targetAttrs($link) ?>><?= e((string) ($link['label'] ?? '')) ?></a></li>
<?php endforeach; ?>
                                    </ul>
<?php endif; ?>
                                </div>
<?php endforeach; ?>
                            </div>
                        </div>
<?php else: ?>
                        <ul class="dropdown-menu">
<?php foreach ($children as $child): ?>
                            <li><a class="dropdown-item" href="<?= e($href($child['url'] ?? '#')) ?>"<?= $targetAttrs($child) ?>><?= e((string) ($child['label'] ?? '')) ?></a></li>
<?php foreach ((array) ($child['children'] ?? []) as $grand): ?>
                            <li><a class="dropdown-item ps-4" href="<?= e($href($grand['url'] ?? '#')) ?>"<?= $targetAttrs($grand) ?>><?= e((string) ($grand['label'] ?? '')) ?></a></li>
<?php endforeach; ?>
<?php endforeach; ?>
                        </ul>
<?php endif; ?>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </div>
<?php endif; ?>
</header>

<!-- Mobil menü: offcanvas + akordiyon -->
<div class="offcanvas offcanvas-start ym-offcanvas" tabindex="-1" id="ymMenuOffcanvas" aria-labelledby="ymMenuOffcanvasLabel">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="ymMenuOffcanvasLabel">Kategoriler</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body">
<?php if ($mobileTree === []): ?>
        <p class="text-muted">Menü şu anda görüntülenemiyor.</p>
        <a class="btn btn-primary" href="<?= e(url('/urunler')) ?>">Tüm ürünler</a>
<?php else: ?>
        <ul class="ym-m-list">
<?php foreach ($mobileTree as $idx => $item):
    $children = (array) ($item['children'] ?? []);
    $label = (string) ($item['label'] ?? '');
    $collapseId = 'ymMenuSub' . $idx;
    ?>
            <li>
<?php if ($children === []): ?>
                <a class="ym-m-link" href="<?= e($href($item['url'] ?? '#')) ?>"<?= $targetAttrs($item) ?>><?= e($label) ?></a>
<?php else: ?>
                <button type="button" class="ym-m-link ym-m-toggle" data-bs-toggle="collapse" data-bs-target="#<?= e($collapseId) ?>" aria-expanded="false" aria-controls="<?= e($collapseId) ?>">
                    <span><?= e($label) ?></span>
                    <svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-chevron-down"></use></svg>
                </button>
                <div class="collapse" id="<?= e($collapseId) ?>">
                    <ul class="ym-m-sub">
                        <li><a class="ym-m-link" href="<?= e($href($item['url'] ?? '#')) ?>"<?= $targetAttrs($item) ?>>Tümünü gör</a></li>
<?php foreach ($children as $child): ?>
                        <li><a class="ym-m-link" href="<?= e($href($child['url'] ?? '#')) ?>"<?= $targetAttrs($child) ?>><?= e((string) ($child['label'] ?? '')) ?></a></li>
<?php foreach ((array) ($child['children'] ?? []) as $grand): ?>
                        <li><a class="ym-m-link ps-3" href="<?= e($href($grand['url'] ?? '#')) ?>"<?= $targetAttrs($grand) ?>><?= e((string) ($grand['label'] ?? '')) ?></a></li>
<?php endforeach; ?>
<?php endforeach; ?>
                    </ul>
                </div>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
        <hr>
        <ul class="ym-m-list">
<?php if ($user): ?>
            <li><a class="ym-m-link" href="<?= e(url('/hesabim')) ?>">Hesabım</a></li>
            <li><a class="ym-m-link" href="<?= e(url('/hesabim/siparisler')) ?>">Siparişlerim</a></li>
<?php if (can('dashboard.view')): ?>
            <li><a class="ym-m-link" href="<?= e(url('/yonetim')) ?>">Yönetim Paneli</a></li>
<?php endif; ?>
            <li>
                <form method="post" action="<?= e(url('/cikis')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="ym-m-link ym-m-toggle">Çıkış</button>
                </form>
            </li>
<?php else: ?>
            <li><a class="ym-m-link" href="<?= e(url('/giris')) ?>">Giriş yap</a></li>
            <li><a class="ym-m-link" href="<?= e(url('/kayit')) ?>">Kayıt ol</a></li>
<?php endif; ?>
        </ul>
    </div>
</div>

<!-- Mini sepet -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="ymCartOffcanvas" aria-labelledby="ymCartOffcanvasLabel">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="ymCartOffcanvasLabel">Sepetim</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column gap-3">
        <p class="mb-0">Sepetinizdeki ürünleri görüntülemek ve siparişinizi tamamlamak için sepet sayfasına gidin.</p>
        <a class="btn btn-primary" href="<?= e(url('/sepet')) ?>">Sepeti görüntüle</a>
        <a class="btn btn-outline-primary" href="<?= e(url('/urunler')) ?>">Alışverişe devam et</a>
    </div>
</div>
