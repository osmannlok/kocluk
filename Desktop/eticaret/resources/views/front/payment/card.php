<?php
// resources/views/front/payment/card.php
// Girdi: $order, $items, $iframeSrc, $remainingMinutes
// CSP notu: config/security.php içinde script-src ve frame-src'ye https://www.paytr.com eklenmiş olmalı.
?>
<section class="container py-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <h1 class="h4 mb-3">Kart ile Ödeme</h1>

            <div class="alert alert-info d-flex align-items-start gap-2" role="status">
                <svg class="flex-shrink-0" width="20" height="20" aria-hidden="true"><use href="#i-lock"></use></svg>
                <div>
                    Kart bilgileriniz sitemizde saklanmaz; ödeme PayTR'nin güvenli ödeme sayfasında alınır.
                    <?php if ((int) $remainingMinutes > 0): ?>
                        <br>Ödeme için kalan süre: yaklaşık <strong><?= e((string) $remainingMinutes) ?> dakika</strong>.
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-2 p-md-3">
                    <iframe src="<?= e($iframeSrc) ?>" id="paytriframe" class="w-100" frameborder="0" scrolling="no" title="Güvenli ödeme formu"></iframe>
                </div>
            </div>
        </div>

        <aside class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">Sipariş Özeti</h2>
                    <p class="small text-muted mb-3">Sipariş No: <strong><?= e($order['order_number']) ?></strong></p>

                    <ul class="list-unstyled small mb-3">
                        <?php foreach ($items as $item): ?>
                            <li class="d-flex justify-content-between gap-2 mb-2">
                                <span>
                                    <?= e($item['product_name']) ?>
                                    <span class="text-muted">(<?= e($item['variant_name']) ?>) × <?= e((string) $item['quantity']) ?></span>
                                </span>
                                <span class="text-nowrap"><?= e(money($item['line_total'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <dl class="row small mb-0">
                        <dt class="col-7 fw-normal">Ara toplam</dt>
                        <dd class="col-5 text-end mb-1"><?= e(money($order['subtotal'])) ?></dd>
                        <?php if ((float) $order['discount_total'] > 0): ?>
                            <dt class="col-7 fw-normal">İndirim</dt>
                            <dd class="col-5 text-end mb-1">-<?= e(money($order['discount_total'])) ?></dd>
                        <?php endif; ?>
                        <dt class="col-7 fw-normal">Kargo</dt>
                        <dd class="col-5 text-end mb-1"><?= (float) $order['shipping_total'] > 0 ? e(money($order['shipping_total'])) : 'Ücretsiz' ?></dd>
                        <dt class="col-7 fw-bold">Toplam</dt>
                        <dd class="col-5 text-end fw-bold mb-0"><?= e(money($order['grand_total'])) ?></dd>
                    </dl>
                </div>
            </div>
        </aside>
    </div>
</section>