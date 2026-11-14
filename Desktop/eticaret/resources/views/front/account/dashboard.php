<?php
// resources/views/front/account/dashboard.php

/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $recentOrders
 * @var ?array<string,mixed> $defaultAddress
 * @var bool $emailVerified
 */

$ymLinks = [
    ['/hesabim/siparisler', 'Siparişlerim', 'i-package'],
    ['/hesabim/adresler', 'Adreslerim', 'i-pin'],
    ['/hesabim/profil', 'Profilim', 'i-user'],
    ['/urunler', 'Alışverişe devam et', 'i-grid'],
    ['/iletisim', 'Bize ulaşın', 'i-mail'],
];
?>
<h1 class="h3 mb-1">Merhaba, <?= e($user['first_name'] ?? '') ?></h1>
<p class="text-body-secondary mb-4">Hesabınızın özeti aşağıdadır.</p>

<?php if (!$emailVerified): ?>
    <div class="alert alert-warning d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2" role="alert">
        <div>
            <strong>E-posta adresiniz doğrulanmamış.</strong>
            Sipariş verebilmek için e-postanıza gönderilen bağlantıyla adresinizi doğrulayın.
        </div>
        <form method="post" action="<?= e(url('/eposta-dogrula/tekrar')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-warning btn-sm text-nowrap">Doğrulama e-postasını yeniden gönder</button>
        </form>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <section class="card shadow-sm h-100" aria-labelledby="ymRecentOrders">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0" id="ymRecentOrders">Son siparişler</h2>
                    <?php if ($recentOrders !== []): ?>
                        <a href="<?= e(url('/hesabim/siparisler')) ?>" class="small">Tümünü gör</a>
                    <?php endif; ?>
                </div>

                <?php if ($recentOrders === []): ?>
                    <p class="text-body-secondary mb-3">Henüz siparişiniz bulunmuyor.</p>
                    <a href="<?= e(url('/urunler')) ?>" class="btn btn-primary btn-sm">Alışverişe başla</a>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentOrders as $order): ?>
                            <li class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <a class="fw-semibold" href="<?= e(url('/hesabim/siparisler/' . rawurlencode((string) $order['order_number']))) ?>"><?= e($order['order_number']) ?></a>
                                    <div class="small text-body-secondary"><?= e($order['date_fmt']) ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge <?= e($order['status_class']) ?>"><?= e($order['status_label']) ?></span>
                                    <div class="fw-semibold mt-1"><?= e(money($order['grand_total'])) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-5">
        <section class="card shadow-sm h-100" aria-labelledby="ymDefaultAddress">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0" id="ymDefaultAddress">Varsayılan adres</h2>
                    <a href="<?= e(url('/hesabim/adresler')) ?>" class="small">Adreslerim</a>
                </div>
                <?php if ($defaultAddress === null): ?>
                    <p class="text-body-secondary mb-3">Kayıtlı adresiniz yok.</p>
                    <a href="<?= e(url('/hesabim/adresler')) ?>" class="btn btn-outline-primary btn-sm">Adres ekle</a>
                <?php else: ?>
                    <address class="mb-0">
                        <strong><?= e($defaultAddress['title'] ?? '') ?></strong><br>
                        <?= e($defaultAddress['full_name'] ?? '') ?><br>
                        <?= e($defaultAddress['address_line'] ?? '') ?><br>
                        <?php if (!empty($defaultAddress['neighborhood'])): ?>
                            <?= e($defaultAddress['neighborhood']) ?>,
                        <?php endif; ?>
                        <?= e($defaultAddress['district'] ?? '') ?> / <?= e($defaultAddress['city'] ?? '') ?><br>
                        <span class="text-body-secondary"><?= e($defaultAddress['phone'] ?? '') ?></span>
                    </address>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<section class="mt-4" aria-labelledby="ymQuickLinks">
    <h2 class="h5 mb-3" id="ymQuickLinks">Hızlı bağlantılar</h2>
    <div class="row g-3">
        <?php foreach ($ymLinks as [$href, $label, $icon]): ?>
            <div class="col-6 col-md-4">
                <a href="<?= e(url($href)) ?>" class="card card-body h-100 text-decoration-none shadow-sm align-items-start gap-2">
                    <svg width="24" height="24" aria-hidden="true" focusable="false"><use href="#<?= e($icon) ?>"></use></svg>
                    <span class="fw-semibold"><?= e($label) ?></span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>