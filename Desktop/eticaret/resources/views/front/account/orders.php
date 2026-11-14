<?php
// resources/views/front/account/orders.php

use App\Core\View;

/**
 * @var array<int,array<string,mixed>> $orders
 * @var array<int,list<array{name:string,image:?string}>> $thumbs
 * @var array{page:int,pages:int,total:int} $pager
 */
?>
<h1 class="h3 mb-3">Siparişlerim</h1>

<?php if ($orders === []): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <svg width="48" height="48" class="text-body-secondary mb-3" aria-hidden="true" focusable="false"><use href="#i-package"></use></svg>
            <h2 class="h5">Henüz siparişiniz yok</h2>
            <p class="text-body-secondary">Yöresel lezzetleri keşfetmeye başlayın.</p>
            <a href="<?= e(url('/urunler')) ?>" class="btn btn-primary">Ürünlere göz at</a>
        </div>
    </div>
<?php else: ?>
    <div class="vstack gap-3">
        <?php foreach ($orders as $order): ?>
            <?php
            $oid = (int) $order['id'];
            $number = (string) $order['order_number'];
            $preview = $thumbs[$oid] ?? [];
            $extra = max(0, (int) ($order['item_count'] ?? 0) - count($preview));
            ?>
            <article class="card shadow-sm" aria-labelledby="ymOrder<?= $oid ?>">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <h2 class="h6 mb-1" id="ymOrder<?= $oid ?>">
                                <a href="<?= e(url('/hesabim/siparisler/' . rawurlencode($number))) ?>"><?= e($number) ?></a>
                            </h2>
                            <div class="small text-body-secondary"><?= e($order['date_fmt']) ?></div>
                        </div>
                        <span class="badge <?= e($order['status_class']) ?>"><?= e($order['status_label']) ?></span>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                        <div class="d-flex align-items-center gap-2">
                            <?php foreach ($preview as $thumb): ?>
                                <?php if ($thumb['image'] !== null): ?>
                                    <img src="<?= e(url($thumb['image'])) ?>" alt="<?= e($thumb['name']) ?>" width="48" height="48"
                                         class="rounded border object-fit-cover" loading="lazy">
                                <?php else: ?>
                                    <span class="d-inline-flex align-items-center justify-content-center rounded border bg-light text-body-secondary" title="<?= e($thumb['name']) ?>">
                                        <svg width="48" height="48" class="p-2" aria-hidden="true" focusable="false"><use href="#i-package"></use></svg>
                                        <span class="visually-hidden"><?= e($thumb['name']) ?></span>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($extra > 0): ?>
                                <span class="small text-body-secondary">+<?= e($extra) ?> ürün</span>
                            <?php endif; ?>
                        </div>

                        <div class="text-end ms-auto">
                            <div class="small text-body-secondary"><?= e($order['payment_method_label']) ?></div>
                            <div class="fw-bold fs-5"><?= e(money($order['grand_total'])) ?></div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <a href="<?= e(url('/hesabim/siparisler/' . rawurlencode($number))) ?>" class="btn btn-outline-primary btn-sm">Sipariş detayı</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($pager['pages'] > 1): ?>
        <div class="mt-4">
            <?= View::partial('partials.pagination', ['pager' => $pager, 'baseUrl' => '/hesabim/siparisler']) ?>
        </div>
    <?php endif; ?>
<?php endif; ?>