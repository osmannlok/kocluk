<?php // resources/views/front/checkout/complete.php
// Girdi: $order, $items, $shipping, $paymentInstructions
$pi = is_array($paymentInstructions ?? null) ? $paymentInstructions : [];
$method = (string) $order['payment_method'];
$fmt = static fn (?string $d): string => ($d !== null && $d !== '') ? date('d.m.Y H:i', (int) strtotime($d)) : '';
?>
<section class="container py-4 ym-order-complete">
    <div class="text-center mb-4">
        <h1 class="h3">Siparişiniz alındı</h1>
        <p class="mb-1">Sipariş numaranız: <strong><?= e($order['order_number']) ?></strong></p>
        <p class="text-muted small mb-0">Sipariş özetini e-posta adresinize de gönderdik.</p>
    </div>

    <div class="row justify-content-center g-4">
        <div class="col-lg-8">
            <?php if ($method === 'bank_transfer'): ?>
                <div class="card mb-4 border-warning">
                    <div class="card-header fw-semibold">Havale / EFT bilgileri</div>
                    <div class="card-body">
                        <p>Lütfen <strong><?= e(money((string) $pi['amount'])) ?></strong> tutarını aşağıdaki hesaba gönderin. Ödemeniz dekont kontrolüyle onaylanır.</p>
                        <dl class="row mb-2">
                            <dt class="col-sm-4">Banka</dt>
                            <dd class="col-sm-8"><?= e($pi['bank_name'] ?? '') ?></dd>
                            <dt class="col-sm-4">Hesap sahibi</dt>
                            <dd class="col-sm-8 d-flex align-items-center gap-2">
                                <span><?= e($pi['account_holder'] ?? '') ?></span>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="<?= e($pi['account_holder'] ?? '') ?>">Kopyala</button>
                            </dd>
                            <dt class="col-sm-4">IBAN</dt>
                            <dd class="col-sm-8 d-flex align-items-center gap-2 flex-wrap">
                                <code class="fs-6"><?= e($pi['iban'] ?? '') ?></code>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="<?= e($pi['iban'] ?? '') ?>">Kopyala</button>
                            </dd>
                            <dt class="col-sm-4">Açıklama</dt>
                            <dd class="col-sm-8 d-flex align-items-center gap-2">
                                <strong><?= e($order['order_number']) ?></strong>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="<?= e($order['order_number']) ?>">Kopyala</button>
                            </dd>
                            <?php if (!empty($pi['deadline'])): ?>
                                <dt class="col-sm-4">Son ödeme zamanı</dt>
                                <dd class="col-sm-8"><strong><?= e($fmt($pi['deadline'])) ?></strong></dd>
                            <?php endif; ?>
                        </dl>
                        <?php if (!empty($pi['note'])): ?><p class="small text-muted mb-1"><?= e($pi['note']) ?></p><?php endif; ?>
                        <p class="small text-muted mb-0">Açıklama alanına sipariş numaranızı yazmayı unutmayın. Süre içinde ödeme yapılmazsa sipariş otomatik iptal edilir.</p>
                    </div>
                </div>
            <?php elseif ($method === 'cod'): ?>
                <div class="card mb-4 border-success">
                    <div class="card-header fw-semibold">Kapıda ödeme</div>
                    <div class="card-body">
                        <p class="mb-1">Teslimatta ödenecek tutar: <strong><?= e(money((string) $order['grand_total'])) ?></strong></p>
                        <p class="small text-muted mb-1">Bu tutara <?= e(money((string) ($pi['cod_fee'] ?? $order['cod_fee']))) ?> kapıda ödeme ücreti dahildir.</p>
                        <p class="small text-muted mb-0">Lütfen kurye geldiğinde ödeme için hazır bulunun. Siparişiniz hazırlanmaya başlamıştır.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <p class="mb-2">Ödemeniz henüz tamamlanmadıysa aşağıdaki bağlantıdan devam edebilirsiniz.</p>
                        <?php if (!empty($pi['pay_url'])): ?>
                            <a class="btn btn-success" href="<?= e($pi['pay_url']) ?>">Ödemeyi tamamla</a>
                        <?php endif; ?>
                        <?php if (!empty($pi['deadline'])): ?>
                            <p class="small text-muted mt-2 mb-0">Son ödeme zamanı: <?= e($fmt($pi['deadline'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Sipariş özeti</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span>
                                <?= e($item['product_name']) ?>
                                <span class="d-block small text-muted"><?= e($item['variant_name']) ?> × <?= e((string) $item['quantity']) ?></span>
                            </span>
                            <span class="text-nowrap"><?= e(money((string) $item['line_total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <dl class="row g-1 p-3 mb-0">
                    <dt class="col-7 fw-normal">Ara toplam</dt>
                    <dd class="col-5 text-end mb-0"><?= e(money((string) $order['subtotal'])) ?></dd>
                    <?php if ((float) $order['discount_total'] > 0): ?>
                        <dt class="col-7 fw-normal">İndirim</dt>
                        <dd class="col-5 text-end mb-0 text-success">-<?= e(money((string) $order['discount_total'])) ?></dd>
                    <?php endif; ?>
                    <dt class="col-7 fw-normal">Kargo</dt>
                    <dd class="col-5 text-end mb-0"><?= (float) $order['shipping_total'] > 0 ? e(money((string) $order['shipping_total'])) : 'Ücretsiz' ?></dd>
                    <?php if ((float) $order['cod_fee'] > 0): ?>
                        <dt class="col-7 fw-normal">Kapıda ödeme ücreti</dt>
                        <dd class="col-5 text-end mb-0"><?= e(money((string) $order['cod_fee'])) ?></dd>
                    <?php endif; ?>
                    <dt class="col-7 border-top pt-2">Genel toplam</dt>
                    <dd class="col-5 text-end border-top pt-2 mb-0 fw-bold"><?= e(money((string) $order['grand_total'])) ?></dd>
                </dl>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Teslimat adresi</div>
                <div class="card-body">
                    <p class="mb-0">
                        <?= e($shipping['full_name'] ?? '') ?><br>
                        <?= e($shipping['address_line'] ?? '') ?><br>
                        <?= e(trim((string) ($shipping['neighborhood'] ?? '') . ' ' . (string) ($shipping['district'] ?? '') . ' / ' . (string) ($shipping['city'] ?? ''))) ?><br>
                        <?= e($shipping['phone'] ?? '') ?>
                    </p>
                </div>
            </div>

            <div class="text-center">
                <a class="btn btn-primary" href="<?= e(url('/hesabim/siparisler/' . rawurlencode((string) $order['order_number']))) ?>">Siparişlerim</a>
                <a class="btn btn-outline-secondary" href="<?= e(url('/urunler')) ?>">Alışverişe devam et</a>
            </div>
        </div>
    </div>
</section>