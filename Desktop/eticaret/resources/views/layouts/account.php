<?php
// resources/views/layouts/account.php

use App\Core\View;

/** @var string $content */

$ymMenu = [
    ['/hesabim', 'Genel Bakış', 'i-home', ['/hesabim']],
    ['/hesabim/siparisler', 'Siparişlerim', 'i-package', ['/hesabim/siparisler', '/hesabim/siparisler/*']],
    ['/hesabim/adresler', 'Adreslerim', 'i-pin', ['/hesabim/adresler', '/hesabim/adresler/*']],
    ['/hesabim/profil', 'Profilim', 'i-user', ['/hesabim/profil']],
    ['/hesabim/sifre', 'Şifre', 'i-lock', ['/hesabim/sifre']],
];

$ymIsActive = static function (array $patterns): bool {
    foreach ($patterns as $pattern) {
        if (is_path($pattern)) {
            return true;
        }
    }
    return false;
};

ob_start();
?>
<div class="container py-3 py-lg-4">
    <div class="row g-4">
        <aside class="col-lg-3" aria-label="Hesap menüsü">
            <!-- Masaüstü: sol menü -->
            <nav class="d-none d-lg-block" aria-label="Hesap menüsü (masaüstü)">
                <div class="list-group shadow-sm">
                    <?php foreach ($ymMenu as [$href, $label, $icon, $patterns]): ?>
                        <?php $active = $ymIsActive($patterns); ?>
                        <a href="<?= e(url($href)) ?>"
                           class="list-group-item list-group-item-action d-flex align-items-center gap-2<?= $active ? ' active' : '' ?>"
                           <?= $active ? 'aria-current="page"' : '' ?>>
                            <svg width="18" height="18" aria-hidden="true" focusable="false"><use href="#<?= e($icon) ?>"></use></svg>
                            <span><?= e($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <form method="post" action="<?= e(url('/cikis')) ?>" class="list-group-item p-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-link text-decoration-none text-danger w-100 text-start px-3 py-2">
                            <svg width="18" height="18" aria-hidden="true" focusable="false"><use href="#i-arrow-right"></use></svg>
                            <span class="ms-2">Çıkış</span>
                        </button>
                    </form>
                </div>
            </nav>

            <!-- Mobil: yatay kaydırmalı sekme çubuğu -->
            <nav class="d-lg-none" aria-label="Hesap menüsü (mobil)">
                <ul class="nav nav-pills flex-nowrap overflow-auto pb-2 text-nowrap">
                    <?php foreach ($ymMenu as [$href, $label, $icon, $patterns]): ?>
                        <?php $active = $ymIsActive($patterns); ?>
                        <li class="nav-item">
                            <a href="<?= e(url($href)) ?>"
                               class="nav-link<?= $active ? ' active' : '' ?>"
                               <?= $active ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                    <li class="nav-item">
                        <form method="post" action="<?= e(url('/cikis')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="nav-link text-danger">Çıkış</button>
                        </form>
                    </li>
                </ul>
            </nav>
        </aside>

        <section class="col-lg-9" aria-label="Hesap içeriği">
            <?= $content ?>
        </section>
    </div>
</div>
<?php
$ymInner = (string) ob_get_clean();

// Ana yerleşim sarmalanır; başlık/meta alanları aktarılır.
$ymPayload = ['content' => $ymInner, 'bodyClass' => 'account-page'];
foreach (['title', 'metaDescription', 'canonical', 'ogImage'] as $ymKey) {
    if (isset($$ymKey)) {
        $ymPayload[$ymKey] = $$ymKey;
    }
}

echo View::partial('layouts.main', $ymPayload);