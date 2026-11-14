<?php
declare(strict_types=1);

// resources/views/partials/mobile-nav.php
// Yalnızca mobil alt sekme çubuğu: Ana Sayfa, Kategoriler, Ara, Sepet, Hesabım. Aktif sekme is_path() ile belirlenir.

$cartCount = isset($cartCount) ? max(0, (int) $cartCount) : 0;
$loggedIn = auth() !== null;

$isHome = is_path('/');
$isSearch = is_path('/ara') || is_path('/ara/*');
$isCart = is_path('/sepet') || is_path('/sepet/*');
$isAccount = is_path('/hesabim') || is_path('/hesabim/*') || is_path('/giris') || is_path('/kayit');
?>
<nav class="ym-mobile-nav d-lg-none" aria-label="Alt gezinme">
    <a class="ym-mobile-nav__item<?= $isHome ? ' is-active' : '' ?>" href="<?= e(url('/')) ?>"<?= $isHome ? ' aria-current="page"' : '' ?>>
        <svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-home"></use></svg>
        <span>Ana Sayfa</span>
    </a>
    <button type="button" class="ym-mobile-nav__item" data-bs-toggle="offcanvas" data-bs-target="#ymMenuOffcanvas" aria-controls="ymMenuOffcanvas">
        <svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-grid"></use></svg>
        <span>Kategoriler</span>
    </button>
    <button type="button" class="ym-mobile-nav__item<?= $isSearch ? ' is-active' : '' ?>" data-search-toggle aria-controls="ymSearch" aria-expanded="false">
        <svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-search"></use></svg>
        <span>Ara</span>
    </button>
    <a class="ym-mobile-nav__item<?= $isCart ? ' is-active' : '' ?>" href="<?= e(url('/sepet')) ?>"<?= $isCart ? ' aria-current="page"' : '' ?>>
        <svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-cart"></use></svg>
        <span>Sepet</span>
        <span class="ym-cart-badge" data-cart-count data-count="<?= e((string) $cartCount) ?>"<?= $cartCount === 0 ? ' hidden' : '' ?>><?= e($cartCount > 99 ? '99+' : (string) $cartCount) ?></span>
    </a>
    <a class="ym-mobile-nav__item<?= $isAccount ? ' is-active' : '' ?>" href="<?= e(url($loggedIn ? '/hesabim' : '/giris')) ?>"<?= $isAccount ? ' aria-current="page"' : '' ?>>
        <svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-user"></use></svg>
        <span>Hesabım</span>
    </a>
</nav>
