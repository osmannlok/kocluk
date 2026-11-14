<?php // resources/views/emails/order-created.php
// Girdi: $name, $order, $items, $paymentInstructions (layouts.email gövdeyi sarar)
$ship = json_decode((string) ($order['shipping_address'] ?? ''), true);
$ship = is_array($ship) ? $ship : [];
$pi = is_array($paymentInstructions ?? null) ? $paymentInstructions : [];
$method = (string) ($order['payment_method'] ?? '');
$fmt = static fn (?string $d): string => ($d !== null && $d !== '') ? date('d.m.Y H:i', (int) strtotime($d)) : '';
$accountUrl = url('/hesabim/siparisler/' . rawurlencode((string) $order['order_number']));
?>
<h1 style="margin:0 0 12px;font-size:20px;">Siparişiniz alındı</h1>
<p style="margin:0 0 12px;">Merhaba <?= e($name ?? '') ?>,</p>
<p style="margin:0 0 16px;"><strong><?= e($order['order_number']) ?></strong> numaralı siparişiniz için teşekkür ederiz.</p>

<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;">
    <thead>
        <tr style="background:#f3f3f3;text-align:left;">
            <th style="border-bottom:1px solid #ddd;">Ürün</th>
            <th style="border-bottom:1px solid #ddd;text-align:center;">Adet</th>
            <th style="border-bottom:1px solid #ddd;text-align:right;">Tutar</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td style="border-bottom:1px solid #eee;">
                    <?= e($item['product_name']) ?><br>
                    <span style="color:#666;font-size:12px;"><?= e($item['variant_name']) ?> · <?= e($item['sku']) ?></span>
                </td>
                <td style="border-bottom:1px solid #eee;text-align:center;"><?= e((string) $item['quantity']) ?></td>
                <td style="border-bottom:1px solid #eee;text-align:right;"><?= e(money((string) $item['line_total'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<table role="presentation" width="100%" cellpadding="4" cellspacing="0" style="margin:0 0 16px;">
    <tr><td>Ara toplam</td><td style="text-align:right;"><?= e(money((string) $order['subtotal'])) ?></td></tr>
    <?php if ((float) $order['discount_total'] > 0): ?>
        <tr><td>İndirim<?= !empty($order['coupon_code']) ? ' (' . e($order['coupon_code']) . ')' : '' ?></td><td style="text-align:right;">-<?= e(money((string) $order['discount_total'])) ?></td></tr>
    <?php endif; ?>
    <tr><td>Kargo</td><td style="text-align:right;"><?= (float) $order['shipping_total'] > 0 ? e(money((string) $order['shipping_total'])) : 'Ücretsiz' ?></td></tr>
    <?php if ((float) $order['cod_fee'] > 0): ?>
        <tr><td>Kapıda ödeme ücreti</td><td style="text-align:right;"><?= e(money((string) $order['cod_fee'])) ?></td></tr>
    <?php endif; ?>
    <tr><td style="border-top:1px solid #ddd;"><strong>Genel toplam</strong></td><td style="border-top:1px solid #ddd;text-align:right;"><strong><?= e(money((string) $order['grand_total'])) ?></strong></td></tr>
</table>

<h2 style="margin:0 0 8px;font-size:16px;">Teslimat adresi</h2>
<p style="margin:0 0 16px;">
    <?= e($ship['full_name'] ?? '') ?><br>
    <?= e($ship['address_line'] ?? '') ?><br>
    <?= e(trim((string) ($ship['neighborhood'] ?? '') . ' ' . (string) ($ship['district'] ?? '') . ' / ' . (string) ($ship['city'] ?? ''))) ?><br>
    <?= e($ship['phone'] ?? '') ?>
</p>

<h2 style="margin:0 0 8px;font-size:16px;">Ödeme</h2>
<?php if ($method === 'bank_transfer'): ?>
    <p style="margin:0 0 8px;">Lütfen <strong><?= e(money((string) ($pi['amount'] ?? $order['grand_total']))) ?></strong> tutarını aşağıdaki hesaba havale/EFT ile gönderin.</p>
    <table role="presentation" cellpadding="4" cellspacing="0" style="margin:0 0 8px;">
        <tr><td>Banka</td><td><strong><?= e($pi['bank_name'] ?? '') ?></strong></td></tr>
        <tr><td>Hesap sahibi</td><td><strong><?= e($pi['account_holder'] ?? '') ?></strong></td></tr>
        <tr><td>IBAN</td><td><strong><?= e($pi['iban'] ?? '') ?></strong></td></tr>
        <tr><td>Açıklama</td><td><strong><?= e($order['order_number']) ?></strong></td></tr>
        <?php if (!empty($pi['deadline'])): ?>
            <tr><td>Son ödeme zamanı</td><td><strong><?= e($fmt($pi['deadline'])) ?></strong></td></tr>
        <?php endif; ?>
    </table>
    <?php if (!empty($pi['note'])): ?><p style="margin:0 0 8px;color:#666;"><?= e($pi['note']) ?></p><?php endif; ?>
    <p style="margin:0 0 16px;color:#666;">Açıklama alanına sipariş numaranızı yazmanız onay sürecini hızlandırır. Süre içinde ödeme yapılmazsa sipariş iptal edilir.</p>
<?php elseif ($method === 'cod'): ?>
    <p style="margin:0 0 16px;">Siparişiniz kapıda ödeme ile hazırlanacaktır. Teslimatta ödenecek tutar: <strong><?= e(money((string) $order['grand_total'])) ?></strong>
        (kapıda ödeme ücreti <?= e(money((string) ($pi['cod_fee'] ?? $order['cod_fee']))) ?> dahil).</p>
<?php else: ?>
    <p style="margin:0 0 8px;">Siparişiniz ödemeniz alındığında hazırlanmaya başlayacaktır.</p>
    <?php if (!empty($pi['pay_url'])): ?>
        <p style="margin:0 0 8px;"><a href="<?= e($pi['pay_url']) ?>" style="display:inline-block;padding:10px 18px;background:#2f6b3a;color:#ffffff;text-decoration:none;border-radius:4px;">Ödemeyi tamamla</a></p>
    <?php endif; ?>
    <?php if (!empty($pi['deadline'])): ?>
        <p style="margin:0 0 16px;color:#666;">Ödeme için son zaman: <?= e($fmt($pi['deadline'])) ?></p>
    <?php endif; ?>
<?php endif; ?>

<p style="margin:0 0 8px;"><a href="<?= e($accountUrl) ?>">Siparişimi görüntüle</a></p>