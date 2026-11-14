<?php
// resources/views/emails/order-paid.php
// Girdi: $name, $order, $items
// Not: e-posta istemcileri harici CSS/CSP bilmez; bu yüzden yalnızca burada satır içi stil kullanılır.
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Ödemeniz alındı</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#222;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:6px;padding:24px;">
                <tr>
                    <td>
                        <h1 style="font-size:20px;margin:0 0 12px;">Ödemeniz alındı</h1>
                        <p style="margin:0 0 12px;">Merhaba <?= e($name ?? '') ?>,</p>
                        <p style="margin:0 0 16px;">
                            <strong><?= e($order['order_number']) ?></strong> numaralı siparişinizin
                            <strong><?= e(money($order['grand_total'])) ?></strong> tutarındaki ödemesi başarıyla alındı.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                            <tr>
                                <th align="left" style="padding:8px 0;border-bottom:1px solid #ddd;">Ürün</th>
                                <th align="center" style="padding:8px 0;border-bottom:1px solid #ddd;">Adet</th>
                                <th align="right" style="padding:8px 0;border-bottom:1px solid #ddd;">Tutar</th>
                            </tr>
                            <?php foreach (($items ?? []) as $item): ?>
                                <tr>
                                    <td style="padding:8px 0;border-bottom:1px solid #eee;">
                                        <?= e($item['product_name']) ?>
                                        <span style="color:#777;">(<?= e($item['variant_name']) ?>)</span>
                                    </td>
                                    <td align="center" style="padding:8px 0;border-bottom:1px solid #eee;"><?= e((string) $item['quantity']) ?></td>
                                    <td align="right" style="padding:8px 0;border-bottom:1px solid #eee;"><?= e(money($item['line_total'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="2" align="right" style="padding:10px 0;"><strong>Toplam</strong></td>
                                <td align="right" style="padding:10px 0;"><strong><?= e(money($order['grand_total'])) ?></strong></td>
                            </tr>
                        </table>

                        <p style="margin:16px 0 8px;">
                            Siparişiniz şimdi hazırlanmaya başlayacak. Ürünler yöresel ve özenle paketlenerek gönderildiğinde
                            kargo takip bilginiz ayrıca e-posta ile iletilecektir.
                        </p>
                        <p style="margin:0;">
                            <a href="<?= e(url('/hesabim/siparisler/' . rawurlencode((string) $order['order_number']))) ?>" style="color:#0d6efd;">Siparişimi görüntüle</a>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>