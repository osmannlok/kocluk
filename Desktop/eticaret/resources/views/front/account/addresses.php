<?php
// resources/views/front/account/addresses.php

use App\Core\View;

/**
 * @var array<int,array<string,mixed>> $addresses  (AddressService::present çıktısı, TC maskeli)
 * @var int $maxAddresses
 */
$ymCount = count($addresses);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Adreslerim</h1>
    <?php if ($ymCount < $maxAddresses): ?>
        <button type="button" class="btn btn-primary" data-address-new>Yeni adres ekle</button>
    <?php endif; ?>
</div>

<?php if ($ymCount >= $maxAddresses): ?>
    <div class="alert alert-info" role="status">En fazla <?= e($maxAddresses) ?> adres kaydedebilirsiniz. Yeni eklemek için mevcut bir adresi silin.</div>
<?php endif; ?>

<?php if ($addresses === []): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <svg width="48" height="48" class="text-body-secondary mb-3" aria-hidden="true" focusable="false"><use href="#i-pin"></use></svg>
            <h2 class="h5">Kayıtlı adresiniz yok</h2>
            <p class="text-body-secondary">Siparişlerinizi hızlandırmak için bir adres ekleyin.</p>
            <button type="button" class="btn btn-primary" data-address-new>Adres ekle</button>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3" data-address-list>
        <?php foreach ($addresses as $address): ?>
            <?php
            $aid = (int) $address['id'];
            $isDefault = !empty($address['is_default']);
            $json = json_encode($address, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>
            <div class="col-12 col-md-6" data-address-card>
                <article class="card shadow-sm h-100<?= $isDefault ? ' border-primary' : '' ?>" aria-labelledby="ymAddr<?= $aid ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 class="h6 mb-0" id="ymAddr<?= $aid ?>"><?= e($address['title'] ?? '') ?></h2>
                            <?php if ($isDefault): ?>
                                <span class="badge text-bg-primary" data-default-badge>Varsayılan</span>
                            <?php endif; ?>
                        </div>

                        <address class="mb-3 flex-grow-1">
                            <?= e($address['full_name'] ?? '') ?><br>
                            <?= e($address['address_line'] ?? '') ?><br>
                            <?php if (!empty($address['neighborhood'])): ?><?= e($address['neighborhood']) ?>, <?php endif; ?>
                            <?= e($address['district'] ?? '') ?> / <?= e($address['city'] ?? '') ?>
                            <?php if (!empty($address['postal_code'])): ?> <?= e($address['postal_code']) ?><?php endif; ?><br>
                            <span class="text-body-secondary"><?= e($address['phone'] ?? '') ?></span>
                            <?php if (($address['invoice_type'] ?? '') === 'corporate'): ?>
                                <br><span class="small text-body-secondary">Kurumsal: <?= e($address['company_name'] ?? '') ?></span>
                            <?php endif; ?>
                        </address>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                    data-address-edit data-address="<?= e((string) $json) ?>">Düzenle</button>
                            <?php if (!$isDefault): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-address-default
                                        data-url="<?= e(url('/hesabim/adresler/' . $aid . '/varsayilan')) ?>">Varsayılan yap</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-danger btn-sm"
                                    data-address-delete
                                    data-title="<?= e($address['title'] ?? '') ?>"
                                    data-url="<?= e(url('/hesabim/adresler/' . $aid . '/sil')) ?>">Sil</button>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= View::partial('partials.address-modal') ?>