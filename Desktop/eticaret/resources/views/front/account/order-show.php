<?php
// resources/views/front/account/order-show.php

use App\Helpers\Money;

/**
 * @var array<string,mixed> $order
 * @var array<int,array<string,mixed>> $items
 * @var list<array{label:string,state:string,at:string}> $steps
 * @var list<array{label:string,class:string,at:string}> $historyRows
 * @var list<array<string,string>> $shipments
 * @var int $installments
 * @var ?array<string,mixed> $instructions
 * @var ?string $payUrl
 * @var array<string,string> $shippingAddress
 * @var array<string,string> $billing
 * @var bool $canCancel
 */

$ymNumber = (string) $order['order_number'];
$ymStatus = (string) $order['status'];
$ymHasPositive = static fn(mixed $v): bool => Money::toCents((string) $v) > 0;
?>
<nav aria-label="Sayfa yolu" class="mb-2">
    <a href="<?= e(url('/hesabim/siparisler')) ?>" class="small">&larr; Siparişlerime dön</a>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Sipariş <?= e($ymNumber) ?></h1>
        <div class="text-body-secondary small"><?= e($order['date_fmt']) ?></div>
    </div>
    <span class="badge fs-6 <?= e($order['status_class']) ?>"><?= e($order['status_label']) ?></span>
</div>

<?php if ($ymStatus === 'cancelled'): ?>
    <div class="alert alert-danger" role="alert">
        Bu sipariş iptal edildi<?= $order['cancelled_at_fmt'] !== '' ? ' (' . e($order['cancelled_at_fmt']) . ')' : '' ?>.
    </div>
<?php elseif ($ymStatus === 'refunded'): ?>
    <div class="alert alert-secondary" role="alert">Bu sipariş için iade yapıldı.</div>
<?php endif; ?>

<?php if ($steps !== []): ?>
    <section class="card shadow-sm mb-4" aria-labelledby="ymSteps">
        <div class="card-body">
            <h2 class="h5 mb-3" id="ymSteps">Sipariş durumu</h2>
            <ol class="list-unstyled d-flex flex-column flex-md-row gap-3 mb-0">
                <?php foreach ($steps as $i => $step): ?>
                    <?php
                    $circle = match ($step['state']) {
                        'done'    => 'text-bg-success',
                        'current' => 'text-bg-primary',
                        default   => 'text-bg-light border',
                    };
                    $hint = match ($step['state']) {
                        'done'    => 'tamamlandı',
                        'current' => 'şu anki adım',
                        default   => 'bekliyor',
                    };
                    ?>
                    <li class="d-flex d-md-block flex-md-fill align-items-center gap-3 text-md-center"
                        <?= $step['state'] === 'current' ? 'aria-current="step"' : '' ?>>
                        <span class="badge rounded-pill <?= e($circle) ?> d-inline-flex align-items-center justify-content-center mb-md-2">
                            <?php if ($step['state'] === 'done'): ?>
                                <svg width="16" height="16" aria-hidden="true" focusable="false"><use href="#i-check"></use></svg>
                            <?php else: ?>
                                <?= e($i + 1) ?>
                            <?php endif; ?>
                        </span>
                        <div>
                            <div class="fw-semibold"><?= e($step['label']) ?><span class="visually-hidden"> (<?= e($hint) ?>)</span></div>
                            <?php if ($step['at'] !== ''): ?>
                                <div class="small text-body-secondary"><?= e($step['at']) ?></div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
<?php endif; ?>

<?php if ($payUrl !== null): ?>
    <div class="alert alert-warning d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2" role="alert">
        <div>
            <strong>Ödemeniz tamamlanmadı.</strong>
            <?php if ($order['expires_at_fmt'] !== ''): ?>
                Son ödeme zamanı: <?= e($order['expires_at_fmt']) ?>.
            <?php endif; ?>
        </div>
        <a href="<?= e(url($payUrl)) ?>" class="btn btn-primary btn-sm text-nowrap">Ödemeyi tamamla</a>
    </div>
<?php endif; ?>

<?php if ($instructions !== null && ($instructions['method'] ?? '') === 'bank_transfer'): ?>
    <section class="card border-warning shadow-sm mb-4" aria-labelledby="ymBank">
        <div class="card-body">
            <h2 class="h5" id="ymBank">Havale / EFT bilgileri</h2>
            <dl class="row mb-2">
                <dt class="col-sm-4">Banka</dt>
                <dd class="col-sm-8"><?= e($instructions['bank_name'] ?? '') ?></dd>
                <dt class="col-sm-4">Hesap sahibi</dt>
                <dd class="col-sm-8"><?= e($instructions['account_holder'] ?? '') ?></dd>
                <dt class="col-sm-4">IBAN</dt>
                <dd class="col-sm-8">
                    <span class="font-monospace"><?= e($instructions['iban'] ?? '') ?></span>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-2"
                            data-copy-text="<?= e($instructions['iban'] ?? '') ?>" data-copy-label="IBAN kopyalandı.">Kopyala</button>
                </dd>
                <dt class="col-sm-4">Tutar</dt>
                <dd class="col-sm-8 fw-semibold"><?= e(money($instructions['amount'] ?? $order['grand_total'])) ?></dd>
                <dt class="col-sm-4">Açıklama</dt>
                <dd class="col-sm-8">Sipariş numaranızı yazın: <strong><?= e($ymNumber) ?></strong></dd>
                <?php if (!empty($instructions['deadline'])): ?>
                    <dt class="col-sm-4">Son ödeme</dt>
                    <dd class="col-sm-8"><?= e($instructions['deadline']) ?></dd>
                <?php endif; ?>
            </dl>
            <?php if (!empty($instructions['note'])): ?>
                <p class="small text-body-secondary mb-0"><?= e($instructions['note']) ?></p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ((string) $order['payment_method'] === 'cod' && !in_array($ymStatus, ['cancelled', 'refunded'], true)): ?>
    <div class="alert alert-info" role="status">
        Kapıda ödeme: teslimatta <strong><?= e(money($order['grand_total'])) ?></strong> ödemeniz gerekir.
    </div>
<?php endif; ?>

<?php if ($shipments !== []): ?>
    <section class="card shadow-sm mb-4" aria-labelledby="ymShipment">
        <div class="card-body">
            <h2 class="h5 mb-3" id="ymShipment">Kargo bilgisi</h2>
            <?php foreach ($shipments as $shipment): ?>
                <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center gap-2 py-2 border-top first-child-no-border">
                    <div>
                        <div><span class="text-body-secondary">Firma:</span> <strong><?= e($shipment['carrier']) ?></strong></div>
                        <div>
                            <span class="text-body-secondary">Takip no:</span>
                            <span class="font-monospace"><?= e($shipment['tracking_number']) ?></span>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-1"
                                    data-copy-text="<?= e($shipment['tracking_number']) ?>" data-copy-label="Takip numarası kopyalandı.">Kopyala</button>
                        </div>
                        <?php if ($shipment['shipped_at'] !== ''): ?>
                            <div class="small text-body-secondary">Kargoya veriliş: <?= e($shipment['shipped_at']) ?></div>
                        <?php endif; ?>
                        <?php if ($shipment['delivered_at'] !== ''): ?>
                            <div class="small text-body-secondary">Teslim: <?= e($shipment['delivered_at']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($shipment['tracking_url'] !== ''): ?>
                        <a href="<?= e($shipment['tracking_url']) ?>" target="_blank" rel="noopener noreferrer"
                           class="btn btn-primary btn-sm">Kargoyu takip et</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="card shadow-sm mb-4" aria-labelledby="ymItems">
    <div class="card-body">
        <h2 class="h5 mb-3" id="ymItems">Ürünler</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Ürün</th>
                        <th scope="col" class="text-end">Adet</th>
                        <th scope="col" class="text-end">Birim fiyat</th>
                        <th scope="col" class="text-end">Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                                <div class="small text-body-secondary"><?= e($item['variant_name']) ?> &middot; SKU: <?= e($item['sku']) ?></div>
                            </td>
                            <td class="text-end"><?= e($item['quantity']) ?></td>
                            <td class="text-end">
                                <?php if (Money::toCents((string) $item['list_price']) > Money::toCents((string) $item['unit_price'])): ?>
                                    <del class="small text-body-secondary"><?= e(money($item['list_price'])) ?></del><br>
                                <?php endif; ?>
                                <?= e(money($item['unit_price'])) ?>
                            </td>
                            <td class="text-end fw-semibold"><?= e(money($item['line_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <dl class="row mt-3 mb-0 justify-content-end">
            <dt class="col-7 col-md-4 text-end fw-normal">Ara toplam</dt>
            <dd class="col-5 col-md-3 text-end"><?= e(money($order['subtotal'])) ?></dd>

            <?php if ($ymHasPositive($order['discount_total'])): ?>
                <dt class="col-7 col-md-4 text-end fw-normal">
                    İndirim<?= !empty($order['coupon_code']) ? ' (' . e($order['coupon_code']) . ')' : '' ?>
                </dt>
                <dd class="col-5 col-md-3 text-end text-success">-<?= e(money($order['discount_total'])) ?></dd>
            <?php endif; ?>

            <dt class="col-7 col-md-4 text-end fw-normal">Kargo</dt>
            <dd class="col-5 col-md-3 text-end"><?= $ymHasPositive($order['shipping_total']) ? e(money($order['shipping_total'])) : 'Ücretsiz' ?></dd>

            <?php if ($ymHasPositive($order['cod_fee'])): ?>
                <dt class="col-7 col-md-4 text-end fw-normal">Kapıda ödeme ücreti</dt>
                <dd class="col-5 col-md-3 text-end"><?= e(money($order['cod_fee'])) ?></dd>
            <?php endif; ?>

            <dt class="col-7 col-md-4 text-end fs-5">Genel toplam</dt>
            <dd class="col-5 col-md-3 text-end fs-5 fw-bold"><?= e(money($order['grand_total'])) ?></dd>

            <?php if ($ymHasPositive($order['tax_total'])): ?>
                <dd class="col-12 col-md-7 text-end small text-body-secondary mb-0">
                    Toplama dahil KDV: <?= e(money($order['tax_total'])) ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
</section>

<section class="card shadow-sm mb-4" aria-labelledby="ymPayment">
    <div class="card-body">
        <h2 class="h5 mb-3" id="ymPayment">Ödeme</h2>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <span><?= e($order['payment_method_label']) ?></span>
            <span class="badge <?= e($order['payment_status_class']) ?>"><?= e($order['payment_status_label']) ?></span>
            <?php if ($installments > 1): ?>
                <span class="small text-body-secondary"><?= e($installments) ?> taksit</span>
            <?php endif; ?>
        </div>
        <?php if ((string) $order['payment_status'] === 'failed'): ?>
            <p class="small text-danger mt-2 mb-0">Ödeme alınamadı. Yeni bir sipariş oluşturabilir veya bizimle iletişime geçebilirsiniz.</p>
        <?php endif; ?>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <section class="card shadow-sm h-100" aria-labelledby="ymShipAddr">
            <div class="card-body">
                <h2 class="h5 mb-3" id="ymShipAddr">Teslimat adresi</h2>
                <address class="mb-0">
                    <strong><?= e($shippingAddress['full_name']) ?></strong><br>
                    <?= e($shippingAddress['address_line']) ?><br>
                    <?php if ($shippingAddress['neighborhood'] !== ''): ?><?= e($shippingAddress['neighborhood']) ?>, <?php endif; ?>
                    <?= e($shippingAddress['district']) ?> / <?= e($shippingAddress['city']) ?>
                    <?php if ($shippingAddress['postal_code'] !== ''): ?> <?= e($shippingAddress['postal_code']) ?><?php endif; ?><br>
                    <span class="text-body-secondary"><?= e($shippingAddress['phone']) ?></span>
                </address>
                <?php if (!empty($order['customer_note'])): ?>
                    <p class="small mt-3 mb-0"><span class="text-body-secondary">Sipariş notunuz:</span> <?= e($order['customer_note']) ?></p>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card shadow-sm h-100" aria-labelledby="ymBillAddr">
            <div class="card-body">
                <h2 class="h5 mb-3" id="ymBillAddr">Fatura bilgileri</h2>
                <?php if ($billing['type'] === 'corporate'): ?>
                    <p class="mb-1"><strong><?= e($billing['company_name']) ?></strong></p>
                    <p class="mb-1 small">Vergi dairesi: <?= e($billing['tax_office']) ?></p>
                    <p class="mb-1 small">Vergi no: <?= e($billing['tax_number']) ?></p>
                <?php else: ?>
                    <p class="mb-1"><strong><?= e($billing['full_name']) ?></strong> <span class="small text-body-secondary">(Bireysel)</span></p>
                    <?php if ($billing['tc_identity_no'] !== ''): ?>
                        <p class="mb-1 small">TC Kimlik No: <?= e($billing['tc_identity_no']) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <p class="small text-body-secondary mb-0">
                    <?= e($billing['address_line']) ?>, <?= e($billing['district']) ?> / <?= e($billing['city']) ?>
                </p>
            </div>
        </section>
    </div>
</div>

<?php if ($historyRows !== []): ?>
    <section class="card shadow-sm mb-4" aria-labelledby="ymHistory">
        <div class="card-body">
            <h2 class="h5 mb-3" id="ymHistory">Sipariş geçmişi</h2>
            <ul class="list-unstyled mb-0">
                <?php foreach ($historyRows as $row): ?>
                    <li class="d-flex justify-content-between align-items-center py-1">
                        <span class="badge <?= e($row['class']) ?>"><?= e($row['label']) ?></span>
                        <span class="small text-body-secondary"><?= e($row['at']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php if ($canCancel): ?>
    <section class="card border-danger-subtle shadow-sm" aria-labelledby="ymCancel">
        <div class="card-body">
            <h2 class="h6" id="ymCancel">Siparişi iptal et</h2>
            <p class="small text-body-secondary">
                Siparişiniz henüz hazırlanmaya başlamadı; bu aşamada iptal edebilirsiniz.
            </p>
            <form method="post"
                  action="<?= e(url('/hesabim/siparisler/' . rawurlencode($ymNumber) . '/iptal')) ?>"
                  data-confirm-submit="Bu siparişi iptal etmek istediğinize emin misiniz? Bu işlem geri alınamaz.">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm">Siparişi iptal et</button>
            </form>
        </div>
    </section>
<?php elseif (in_array($ymStatus, ['preparing', 'shipped', 'delivered'], true)): ?>
    <p class="small text-body-secondary">
        Bu sipariş artık çevrimiçi iptal edilemez. İptal veya iade talepleriniz için
        <a href="<?= e(url('/iletisim')) ?>">bizimle iletişime geçin</a>.
    </p>
<?php endif; ?>