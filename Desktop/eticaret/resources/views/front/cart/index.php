<?php
// resources/views/front/cart/index.php
/** @var array $summary @var string $checkoutHref */
$initial = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<section class="container py-3 py-lg-4" data-cart-page data-initial="<?= e($initial) ?>">
    <h1 class="h3 mb-3">Sepetim</h1>

    <noscript>
        <div class="alert alert-warning">Sepetinizi görüntülemek için tarayıcınızda JavaScript etkin olmalıdır.</div>
    </noscript>

    <div data-cart-empty class="text-center py-5 d-none">
        <svg width="48" height="48" aria-hidden="true" focusable="false"><use href="#i-cart"></use></svg>
        <p class="h5 mt-3">Sepetiniz boş</p>
        <p class="text-muted">Yöresel lezzetlerimizi keşfetmeye başlayın.</p>
        <a class="btn btn-primary" href="<?= e(url('/urunler')) ?>">Alışverişe başla</a>
    </div>

    <div data-cart-content class="row g-4 d-none">
        <div class="col-lg-8">
            <div data-alerts aria-live="polite"></div>

            <div data-free-box class="card mb-3 d-none">
                <div class="card-body py-3">
                    <p class="mb-2 small" data-free-text></p>
                    <div class="progress" role="progressbar" aria-label="Ücretsiz kargo ilerlemesi"
                         aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-free-progress>
                        <div class="progress-bar bg-success" data-free-bar data-width="0"></div>
                    </div>
                </div>
            </div>

            <div data-cart-lines></div>
        </div>

        <aside class="col-lg-4">
            <div class="card sticky-lg-top">
                <div class="card-body">
                    <h2 class="h5 mb-3">Sipariş özeti</h2>

                    <form data-coupon-form class="mb-3" novalidate>
                        <label for="ymCouponCode" class="form-label small mb-1">İndirim kuponu</label>
                        <div class="input-group">
                            <input type="text" class="form-control text-uppercase" id="ymCouponCode" name="code"
                                   maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false">
                            <button type="submit" class="btn btn-outline-primary">Uygula</button>
                        </div>
                        <div class="small text-danger mt-1 d-none" data-coupon-feedback role="alert"></div>
                    </form>

                    <div data-coupon-applied class="d-none d-flex align-items-center justify-content-between mb-3">
                        <span class="badge text-bg-success fs-6" data-coupon-code></span>
                        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-coupon-remove>Kuponu kaldır</button>
                    </div>

                    <dl class="row mb-0">
                        <dt class="col-7 fw-normal">Ara toplam</dt>
                        <dd class="col-5 text-end" data-sum="subtotal"></dd>

                        <dt class="col-7 fw-normal text-success d-none" data-row="discount">İndirim</dt>
                        <dd class="col-5 text-end text-success d-none" data-row="discount" data-sum="discount_total"></dd>

                        <dt class="col-7 fw-normal">Kargo</dt>
                        <dd class="col-5 text-end" data-sum="shipping_total"></dd>

                        <dt class="col-7 border-top pt-2 mt-2">Toplam</dt>
                        <dd class="col-5 text-end border-top pt-2 mt-2 fw-bold fs-5" data-sum="grand_total"></dd>
                    </dl>
                    <p class="small text-muted mb-3">Fiyatlara KDV dahildir.</p>

                    <a class="btn btn-primary btn-lg w-100" data-checkout href="<?= e(url($checkoutHref)) ?>">Ödemeye geç</a>
                </div>
            </div>
        </aside>
    </div>
</section>

<div class="d-none d-lg-none fixed-bottom bg-white border-top shadow-sm p-2" data-sticky-bar>
    <div class="d-flex align-items-center justify-content-between gap-3 container">
        <div>
            <div class="small text-muted">Toplam</div>
            <strong data-sum="grand_total"></strong>
        </div>
        <a class="btn btn-primary" data-checkout href="<?= e(url($checkoutHref)) ?>">Ödemeye geç</a>
    </div>
</div>
<div class="d-lg-none pb-5" aria-hidden="true"></div>

<template id="ymCartLineTpl">
    <article class="card mb-3" data-line>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-3 col-md-2">
                    <a data-field="imageLink" href="#" tabindex="-1">
                        <img data-field="image" class="img-fluid rounded" alt="" loading="lazy" width="120" height="120">
                    </a>
                </div>
                <div class="col-9 col-md-10">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <a data-field="name" class="fw-semibold text-decoration-none" href="#"></a>
                            <div class="small text-muted" data-field="variant"></div>
                            <div class="small">
                                <span data-field="unit"></span>
                                <span class="text-muted text-decoration-line-through ms-1 d-none" data-field="list"></span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-link text-danger p-0" data-remove-line aria-label="Ürünü sepetten çıkar">
                            <svg width="20" height="20" aria-hidden="true" focusable="false"><use href="#i-x"></use></svg>
                        </button>
                    </div>
                    <div class="small text-danger mt-1 d-none" data-field="note" role="alert"></div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="input-group input-group-sm w-auto" role="group" aria-label="Adet">
                            <button type="button" class="btn btn-outline-secondary" data-qty-dec aria-label="Adedi azalt">−</button>
                            <span class="input-group-text bg-white px-3" data-qty-value aria-live="polite"></span>
                            <button type="button" class="btn btn-outline-secondary" data-qty-inc aria-label="Adedi artır">+</button>
                        </div>
                        <strong data-field="lineTotal"></strong>
                    </div>
                </div>
            </div>
        </div>
    </article>
</template>