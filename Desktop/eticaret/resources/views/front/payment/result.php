<?php
// resources/views/front/payment/result.php
// Girdi: $state (success|pending|failed), $order, $canRetry
$orderNumber = (string) $order['order_number'];

$view = match ($state) {
    'success' => [
        'class' => 'success', 'icon' => '#i-check',
        'heading' => 'Ödemeniz alındı',
        'text' => 'Siparişiniz hazırlanmak üzere işleme alındı. Onay e-postası gönderilecektir.',
    ],
    'pending' => [
        'class' => 'warning', 'icon' => '#i-package',
        'heading' => 'Ödemeniz doğrulanıyor',
        'text' => 'Ödeme sonucu bankadan teyit ediliyor. Bu işlem birkaç dakika sürebilir; sipariş durumunu hesabınızdan takip edebilirsiniz.',
    ],
    default => [
        'class' => 'danger', 'icon' => '#i-x',
        'heading' => 'Ödeme tamamlanamadı',
        'text' => 'Ödeme işlemi tamamlanamadı. Kartınızdan çekim yapılmadıysa tekrar deneyebilirsiniz.',
    ],
};
?>
<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">
            <div class="text-<?= e($view['class']) ?> mb-3">
                <svg width="56" height="56" aria-hidden="true"><use href="<?= e($view['icon']) ?>"></use></svg>
            </div>
            <h1 class="h4 mb-2"><?= e($view['heading']) ?></h1>
            <p class="text-muted mb-1"><?= e($view['text']) ?></p>
            <p class="small mb-4">Sipariş No: <strong><?= e($orderNumber) ?></strong></p>

            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <?php if ($state === 'failed'): ?>
                    <?php if (!empty($canRetry)): ?>
                        <a class="btn btn-primary" href="<?= e(url('/odeme/kart/' . rawurlencode($orderNumber))) ?>">Tekrar dene</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/sepet')) ?>">Sepete dön</a>
                <?php elseif ($state === 'pending'): ?>
                    <a class="btn btn-primary" href="<?= e(url('/hesabim/siparisler')) ?>">Siparişlerim</a>
                <?php else: ?>
                    <a class="btn btn-primary" href="<?= e(url('/hesabim/siparisler/' . rawurlencode($orderNumber))) ?>">Siparişi görüntüle</a>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/urunler')) ?>">Alışverişe devam et</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>