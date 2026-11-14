<?php
// resources/views/partials/mini-cart.php
// Header sepet offcanvas'ı; içeriği cart.js /sepet/ozet'ten doldurur.
$checkoutHref = auth() !== null ? '/odeme' : '/giris';
?>
<div class="offcanvas offcanvas-end" tabindex="-1" id="ymCartOffcanvas" aria-labelledby="ymCartOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title h5" id="ymCartOffcanvasLabel">Sepetim</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
        <div data-mini-empty class="text-center p-4 d-none">
            <p class="mb-3 text-muted">Sepetiniz boş.</p>
            <a class="btn btn-outline-primary" href="<?= e(url('/urunler')) ?>">Ürünlere göz at</a>
        </div>

        <ul class="list-unstyled m-0 flex-grow-1 overflow-auto" data-mini-list aria-live="polite"></ul>

        <div data-mini-footer class="border-top p-3 d-none">
            <div class="d-flex justify-content-between mb-3">
                <span>Ara toplam</span>
                <strong data-mini-subtotal></strong>
            </div>
            <div class="d-grid gap-2">
                <a class="btn btn-outline-primary" href="<?= e(url('/sepet')) ?>">Sepete git</a>
                <a class="btn btn-primary" data-checkout href="<?= e(url($checkoutHref)) ?>">Ödemeye geç</a>
            </div>
        </div>
    </div>

    <template id="ymMiniCartItemTpl">
        <li class="d-flex gap-3 p-3 border-bottom" data-line>
            <img data-field="image" class="rounded" alt="" width="56" height="56" loading="lazy">
            <div class="flex-grow-1">
                <a data-field="name" class="fw-semibold small text-decoration-none" href="#"></a>
                <div class="small text-muted" data-field="variant"></div>
                <div class="small"><span data-field="qty"></span> × <span data-field="unit"></span></div>
            </div>
            <strong class="small" data-field="lineTotal"></strong>
        </li>
    </template>
</div>