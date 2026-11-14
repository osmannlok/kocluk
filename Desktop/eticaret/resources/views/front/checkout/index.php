<?php // resources/views/front/checkout/index.php
// Girdi: $addresses, $selectedAddressId, $paymentMethods, $selectedMethod, $summary, $nonce, $codFee, $legalLinks
$errs = errors();
$errLines = [];
if (is_array($errs)) {
    foreach ($errs as $m) {
        foreach ((array) $m as $line) {
            $errLines[] = (string) $line;
        }
    }
}
$hasErrors = $errLines !== [];
$blocking = array_map('strval', (array) ($summary['blocking_errors'] ?? []));
$showCls = $hasErrors ? ' show' : '';
$btnCls = $hasErrors ? '' : ' collapsed';
$expanded = $hasErrors ? 'true' : 'false';
$billingType = (string) old('billing_type', 'individual');
$billingType = $billingType === 'corporate' ? 'corporate' : 'individual';
$initialJson = json_encode(array_values($addresses), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$threshold = (string) ($summary['free_shipping_threshold'] ?? '0.00');
?>
<section class="container py-3 py-lg-4 ym-checkout">
    <h1 class="h3 mb-3">Ödeme</h1>

    <div id="ymCheckoutErrors" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
    <?php if ($hasErrors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errLines as $line): ?>
                    <li><?= e($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <div id="ymBlockingErrors" class="alert alert-warning<?= $blocking === [] ? ' d-none' : '' ?>" role="alert">
        <ul class="mb-1">
            <?php foreach ($blocking as $line): ?>
                <li><?= e($line) ?></li>
            <?php endforeach; ?>
        </ul>
        <a href="<?= e(url('/sepet')) ?>" class="alert-link">Sepete dön</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8 order-2 order-lg-1">
            <form id="ymCheckoutForm" method="post" action="<?= e(url('/odeme/siparis-ver')) ?>" novalidate data-no-lock>
                <?= csrf_field() ?>
                <input type="hidden" name="nonce" value="<?= e($nonce) ?>">

                <div class="accordion" id="ymCheckoutSteps">
                    <!-- 1. Adres -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#ymStep1" aria-expanded="true" aria-controls="ymStep1">1. Teslimat adresi</button>
                        </h2>
                        <div id="ymStep1" class="accordion-collapse collapse show">
                            <div class="accordion-body">
                                <div id="ymAddressList" class="row g-2" data-initial="<?= e($initialJson) ?>" data-selected="<?= e((string) $selectedAddressId) ?>"></div>
                                <p id="ymAddressEmpty" class="text-muted d-none mb-2">Kayıtlı adresiniz yok. Devam etmek için yeni bir adres ekleyin.</p>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-address-new>Yeni adres ekle</button>
                                <div class="mt-3 text-end">
                                    <button type="button" class="btn btn-primary" data-step-next="ymStep2">Devam</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Fatura -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button<?= $btnCls ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ymStep2" aria-expanded="<?= $expanded ?>" aria-controls="ymStep2">2. Fatura bilgileri</button>
                        </h2>
                        <div id="ymStep2" class="accordion-collapse collapse<?= $showCls ?>">
                            <div class="accordion-body">
                                <div class="mb-3">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="billing_type" id="ymBillInd" value="individual"<?= $billingType === 'individual' ? ' checked' : '' ?>>
                                        <label class="form-check-label" for="ymBillInd">Bireysel</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="billing_type" id="ymBillCorp" value="corporate"<?= $billingType === 'corporate' ? ' checked' : '' ?>>
                                        <label class="form-check-label" for="ymBillCorp">Kurumsal</label>
                                    </div>
                                </div>
                                <div data-billing-panel="individual"<?= $billingType === 'individual' ? '' : ' hidden' ?>>
                                    <label for="ymBillTc" class="form-label">TC kimlik no <span class="text-muted">(opsiyonel)</span></label>
                                    <input type="text" class="form-control" id="ymBillTc" name="billing_tc_identity_no" inputmode="numeric" maxlength="11" autocomplete="off" value="">
                                    <div class="form-text">Faturada TC kimlik numarası istemiyorsanız boş bırakabilirsiniz.</div>
                                </div>
                                <div data-billing-panel="corporate"<?= $billingType === 'corporate' ? '' : ' hidden' ?>>
                                    <div class="mb-2">
                                        <label for="ymBillCompany" class="form-label">Şirket adı</label>
                                        <input type="text" class="form-control" id="ymBillCompany" name="billing_company_name" maxlength="190" value="<?= e(old('billing_company_name', '')) ?>">
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label for="ymBillOffice" class="form-label">Vergi dairesi</label>
                                            <input type="text" class="form-control" id="ymBillOffice" name="billing_tax_office" maxlength="120" value="<?= e(old('billing_tax_office', '')) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="ymBillTax" class="form-label">Vergi numarası</label>
                                            <input type="text" class="form-control" id="ymBillTax" name="billing_tax_number" inputmode="numeric" maxlength="11" value="<?= e(old('billing_tax_number', '')) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 text-end">
                                    <button type="button" class="btn btn-primary" data-step-next="ymStep3">Devam</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Ödeme -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button<?= $btnCls ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ymStep3" aria-expanded="<?= $expanded ?>" aria-controls="ymStep3">3. Ödeme yöntemi</button>
                        </h2>
                        <div id="ymStep3" class="accordion-collapse collapse<?= $showCls ?>">
                            <div class="accordion-body">
                                <?php if ($paymentMethods === []): ?>
                                    <p class="text-danger mb-0">Şu anda aktif bir ödeme yöntemi bulunmuyor.</p>
                                <?php endif; ?>
                                <?php foreach ($paymentMethods as $pm): ?>
                                    <div class="form-check border rounded p-3 ps-5 mb-2">
                                        <input class="form-check-input" type="radio" name="payment_method" id="ymPay_<?= e($pm['code']) ?>" value="<?= e($pm['code']) ?>"<?= $selectedMethod === $pm['code'] ? ' checked' : '' ?>>
                                        <label class="form-check-label d-block" for="ymPay_<?= e($pm['code']) ?>">
                                            <span class="fw-semibold d-block"><?= e($pm['label']) ?></span>
                                            <span class="small text-muted"><?= e($pm['description']) ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <div class="mt-3 text-end">
                                    <button type="button" class="btn btn-primary" data-step-next="ymStep4">Devam</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Not -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button<?= $btnCls ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ymStep4" aria-expanded="<?= $expanded ?>" aria-controls="ymStep4">4. Sipariş notu</button>
                        </h2>
                        <div id="ymStep4" class="accordion-collapse collapse<?= $showCls ?>">
                            <div class="accordion-body">
                                <label for="ymNote" class="form-label">Kuryeye veya satıcıya notunuz <span class="text-muted">(opsiyonel)</span></label>
                                <textarea class="form-control" id="ymNote" name="customer_note" rows="3" maxlength="500"><?= e(old('customer_note', '')) ?></textarea>
                                <div class="mt-3 text-end">
                                    <button type="button" class="btn btn-primary" data-step-next="ymStep5">Devam</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Onaylar -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button<?= $btnCls ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ymStep5" aria-expanded="<?= $expanded ?>" aria-controls="ymStep5">5. Onaylar</button>
                        </h2>
                        <div id="ymStep5" class="accordion-collapse collapse<?= $showCls ?>">
                            <div class="accordion-body">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="accept_distance_sales" id="ymAcceptDistance" value="1" required>
                                    <label class="form-check-label" for="ymAcceptDistance">
                                        <?php if (!empty($legalLinks['distance_sales'])): ?>
                                            <a href="<?= e(url($legalLinks['distance_sales'])) ?>" target="_blank" rel="noopener">Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu</a>'nu
                                        <?php else: ?>
                                            Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu'nu
                                        <?php endif; ?>
                                        okudum, kabul ediyorum.
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="accept_kvkk" id="ymAcceptKvkk" value="1" required>
                                    <label class="form-check-label" for="ymAcceptKvkk">
                                        <?php if (!empty($legalLinks['kvkk'])): ?>
                                            <a href="<?= e(url($legalLinks['kvkk'])) ?>" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>'ni
                                        <?php else: ?>
                                            KVKK Aydınlatma Metni'ni
                                        <?php endif; ?>
                                        okudum.
                                    </label>
                                </div>
                                <p class="small text-muted mb-0">Gıda ürünlerinde, niteliği gereği iade edilemeyen ürünler için cayma hakkı istisnası uygulanır; ayrıntılar sözleşmede yer alır.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-lg-none mt-3 d-grid">
                    <button type="submit" class="btn btn-success btn-lg" data-place-order<?= $blocking !== [] ? ' disabled' : '' ?>>Siparişi onayla</button>
                </div>
            </form>
        </div>

        <aside class="col-lg-4 order-1 order-lg-2">
            <div class="card sticky-lg-top">
                <div class="card-header">
                    <button class="btn btn-link p-0 d-lg-none text-start w-100 text-decoration-none d-flex justify-content-between" type="button" data-bs-toggle="collapse" data-bs-target="#ymSummaryBody" aria-expanded="false" aria-controls="ymSummaryBody">
                        <span>Sipariş özeti</span>
                        <strong data-sum="grand_total"><?= e(money((string) $summary['grand_total'])) ?></strong>
                    </button>
                    <h2 class="h6 mb-0 d-none d-lg-block">Sipariş özeti</h2>
                </div>
                <div id="ymSummaryBody" class="collapse d-lg-block">
                    <ul class="list-group list-group-flush">
                        <?php foreach ((array) ($summary['lines'] ?? []) as $line): ?>
                            <li class="list-group-item d-flex justify-content-between gap-2">
                                <span>
                                    <?= e($line['product_name']) ?>
                                    <span class="d-block small text-muted"><?= e($line['variant_name']) ?> × <?= e((string) $line['quantity']) ?></span>
                                </span>
                                <span class="text-nowrap"><?= e(money((string) $line['line_total'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <dl class="row g-1 p-3 mb-0">
                        <dt class="col-7 fw-normal">Ara toplam</dt>
                        <dd class="col-5 text-end mb-0" data-sum="subtotal"><?= e(money((string) $summary['subtotal'])) ?></dd>

                        <div class="row g-0 m-0 p-0<?= (float) $summary['discount_total'] > 0 ? '' : ' d-none' ?>" data-sum-row="discount_total">
                            <dt class="col-7 fw-normal">İndirim</dt>
                            <dd class="col-5 text-end mb-0 text-success">-<span data-sum="discount_total"><?= e(money((string) $summary['discount_total'])) ?></span></dd>
                        </div>

                        <dt class="col-7 fw-normal">Kargo</dt>
                        <dd class="col-5 text-end mb-0" data-sum="shipping_total"><?= !empty($summary['shipping_is_free']) ? 'Ücretsiz' : e(money((string) $summary['shipping_total'])) ?></dd>

                        <div class="row g-0 m-0 p-0<?= (float) $summary['cod_fee'] > 0 ? '' : ' d-none' ?>" data-sum-row="cod_fee">
                            <dt class="col-7 fw-normal">Kapıda ödeme ücreti</dt>
                            <dd class="col-5 text-end mb-0" data-sum="cod_fee"><?= e(money((string) $summary['cod_fee'])) ?></dd>
                        </div>

                        <dt class="col-7 border-top pt-2">Genel toplam</dt>
                        <dd class="col-5 text-end border-top pt-2 mb-0 fw-bold" data-sum="grand_total"><?= e(money((string) $summary['grand_total'])) ?></dd>
                    </dl>
                    <p class="small text-muted px-3 mb-2">KDV dahil fiyatlardır.<?= (float) $threshold > 0 ? ' Ücretsiz kargo eşiği: ' . e(money($threshold)) . '.' : '' ?></p>
                    <div class="px-3 pb-3 d-none d-lg-grid">
                        <button type="submit" form="ymCheckoutForm" class="btn btn-success btn-lg" data-place-order<?= $blocking !== [] ? ' disabled' : '' ?>>Siparişi onayla</button>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <template id="ymAddressCardTpl">
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-body d-flex gap-2">
                    <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="shipping_address_id" value="" form="ymCheckoutForm">
                    <label class="flex-grow-1" data-f="label">
                        <span class="fw-semibold d-block"><span data-f="title"></span> <span class="badge text-bg-secondary d-none" data-f="default">Varsayılan</span></span>
                        <span class="d-block small" data-f="person"></span>
                        <span class="d-block small text-muted" data-f="address"></span>
                    </label>
                    <button type="button" class="btn btn-link btn-sm p-0 align-self-start" data-address-edit-card>Düzenle</button>
                </div>
            </div>
        </div>
    </template>
</section>

<?= \App\Core\View::partial('partials.address-modal') ?>