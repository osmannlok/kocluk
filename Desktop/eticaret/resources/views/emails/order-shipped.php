<?php
// resources/views/emails/order-shipped.php
// Girdi: $name, $order (order_number dahil), $shipment (carrier, tracking_number, tracking_url)
declare(strict_types=1);

$orderNumber = (string) ($order['order_number'] ?? '');
$carrier = (string) ($shipment['carrier'] ?? '');
$trackingNumber = (string) ($shipment['tracking_number'] ?? '');
$trackingUrl = (string) ($shipment['tracking_url'] ?? '');

// Güvenlik: yalnızca https:// bağlantılar düğmeye dönüştürülür (javascript:, http: vb. basılmaz).
$hasTrackingLink = $trackingUrl !== '' && str_starts_with($trackingUrl, 'https://');
?>
<h1 style="font-size:20px;margin:0 0 16px;">Siparişiniz kargoya verildi</h1>

<p style="margin:0 0 12px;">Merhaba <?= e($name) ?>,</p>

<p style="margin:0 0 16px;">
    <strong><?= e($orderNumber) ?></strong> numaralı siparişiniz kargoya teslim edildi.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 16px;border-collapse:collapse;">
    <tr>
        <td style="padding:8px 0;color:#666;width:40%;">Kargo firması</td>
        <td style="padding:8px 0;"><strong><?= e($carrier) ?></strong></td>
    </tr>
    <tr>
        <td style="padding:8px 0;color:#666;">Takip numarası</td>
        <td style="padding:8px 0;"><strong><?= e($trackingNumber) ?></strong></td>
    </tr>
</table>

<?php if ($hasTrackingLink): ?>
    <p style="margin:0 0 16px;">
        <a href="<?= e($trackingUrl) ?>" rel="noopener noreferrer" target="_blank"
           style="display:inline-block;padding:12px 24px;background:#8b5a2b;color:#ffffff;text-decoration:none;border-radius:4px;">
            Kargomu Takip Et
        </a>
    </p>
<?php else: ?>
    <p style="margin:0 0 16px;color:#666;">
        Kargonuzu yukarıdaki takip numarasıyla kargo firmasının kanallarından sorgulayabilirsiniz.
    </p>
<?php endif; ?>

<p style="margin:0 0 12px;">
    Tahmini teslim süresi bulunduğunuz bölgeye ve kargo firmasının yoğunluğuna göre değişebilir.
    Ürünlerimiz gıda olduğundan teslimat sırasında ulaşılabilir olmanızı rica ederiz.
</p>

<p style="margin:0;">Bizi tercih ettiğiniz için teşekkür ederiz.</p>