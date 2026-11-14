<?php
declare(strict_types=1);

// resources/views/partials/footer.php
// Altbilgi: 4 sütun (marka+sosyal, footer_1, footer_2, iletişim), güven rozetleri, yasal unvan/vergi satırı, telif.

use App\Models\Menu;
use App\Models\MenuItem;

$settings = isset($settings) && is_array($settings) ? $settings : [];
$siteName = (string) ($settings['site.name'] ?? 'Yöresel Market');
$tagline = (string) ($settings['site.tagline'] ?? '');

$menuModel = new Menu();
$itemModel = new MenuItem();

// Altbilgi menüleri: sütun başlığı menü adından gelir.
$loadMenu = static function (string $location) use ($menuModel, $itemModel): array {
    $menu = $menuModel->findByLocation($location);
    if (!$menu) {
        return ['name' => '', 'items' => []];
    }
    return ['name' => (string) ($menu['name'] ?? ''), 'items' => $itemModel->treeByMenu((int) $menu['id'])];
};
$cols = [$loadMenu('footer_1'), $loadMenu('footer_2')];

$href = static function (mixed $u): string {
    $u = (string) $u;
    return str_starts_with($u, '/') ? url($u) : ($u === '' ? '#' : $u);
};

// Sosyal bağlantılar yalnızca http(s) ise basılır (ayar değeri bozuksa javascript: vb. engellenir).
$safeExternal = static function (mixed $v): string {
    $v = trim((string) $v);
    return preg_match('#^https?://#i', $v) === 1 ? $v : '';
};
$instagram = $safeExternal($settings['social.instagram'] ?? '');
$facebook = $safeExternal($settings['social.facebook'] ?? '');
$whatsappDigits = preg_replace('/\D+/', '', (string) ($settings['contact.whatsapp'] ?? '')) ?? '';
$phone = trim((string) ($settings['contact.phone'] ?? ''));
$phoneHref = preg_replace('/[^\d+]/', '', $phone) ?? '';
$email = trim((string) ($settings['contact.email'] ?? ''));
$address = trim((string) ($settings['contact.address'] ?? ''));

$threshold = (string) ($settings['shipping.free_threshold'] ?? '0');
$freeShipText = is_numeric($threshold) && (float) $threshold > 0
    ? money($threshold) . ' üzeri ücretsiz kargo'
    : 'Özenli paketleme ve hızlı teslimat';

$legalTitle = trim((string) ($settings['legal.company_title'] ?? ''));
$taxOffice = trim((string) ($settings['legal.tax_office'] ?? ''));
$taxNumber = trim((string) ($settings['legal.tax_number'] ?? ''));
$mersis = trim((string) ($settings['legal.mersis'] ?? ''));
?>
<footer class="ym-footer">
    <div class="ym-footer__trust">
        <div class="container">
            <div class="row row-cols-2 row-cols-lg-4 g-0">
                <div class="col"><div class="ym-footer__trust-item"><svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-lock"></use></svg><span>Güvenli ödeme</span></div></div>
                <div class="col"><div class="ym-footer__trust-item"><svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-truck"></use></svg><span><?= e($freeShipText) ?></span></div></div>
                <div class="col"><div class="ym-footer__trust-item"><svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-leaf"></use></svg><span>Yöresel üreticilerden</span></div></div>
                <div class="col"><div class="ym-footer__trust-item"><svg class="ym-icon ym-icon--lg" aria-hidden="true" focusable="false"><use href="#i-shield"></use></svg><span>Kişisel verileriniz korunur</span></div></div>
            </div>
        </div>
    </div>

    <div class="container py-4 py-lg-5">
        <div class="row g-4">
            <div class="col-12 col-md-6 col-lg-3">
                <span class="ym-logo-mask" role="img" aria-label="<?= e($siteName) ?>"></span>
<?php if ($tagline !== ''): ?>
                <p class="mt-3 mb-0"><?= e($tagline) ?></p>
<?php endif; ?>
                <div class="ym-footer__social">
<?php if ($instagram !== ''): ?>
                    <a href="<?= e($instagram) ?>" target="_blank" rel="noopener noreferrer me" aria-label="Instagram"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-instagram"></use></svg></a>
<?php endif; ?>
<?php if ($facebook !== ''): ?>
                    <a href="<?= e($facebook) ?>" target="_blank" rel="noopener noreferrer me" aria-label="Facebook"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-facebook"></use></svg></a>
<?php endif; ?>
<?php if ($whatsappDigits !== ''): ?>
                    <a href="<?= e('https://wa.me/' . $whatsappDigits) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp ile yazın"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-whatsapp"></use></svg></a>
<?php endif; ?>
                </div>
            </div>

<?php foreach ($cols as $col): ?>
            <div class="col-6 col-md-3 col-lg-2">
<?php if ($col['items'] !== []): ?>
                <h2 class="ym-footer__title"><?= e($col['name']) ?></h2>
                <ul class="ym-footer__list">
<?php foreach ($col['items'] as $item): ?>
                    <li><a href="<?= e($href($item['url'] ?? '#')) ?>"<?= !empty($item['open_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e((string) ($item['label'] ?? '')) ?></a></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
<?php endforeach; ?>

            <div class="col-12 col-md-6 col-lg-5">
                <h2 class="ym-footer__title">İletişim</h2>
                <ul class="ym-footer__list ym-footer__contact">
<?php if ($phone !== '' && $phoneHref !== ''): ?>
                    <li><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-phone"></use></svg><a href="<?= e('tel:' . $phoneHref) ?>"><?= e($phone) ?></a></li>
<?php endif; ?>
<?php if ($email !== ''): ?>
                    <li><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-mail"></use></svg><a href="<?= e('mailto:' . $email) ?>"><?= e($email) ?></a></li>
<?php endif; ?>
<?php if ($address !== ''): ?>
                    <li><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-pin"></use></svg><span><?= e($address) ?></span></li>
<?php endif; ?>
                    <li><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-mail"></use></svg><a href="<?= e(url('/iletisim')) ?>">İletişim formu</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="ym-footer__legal">
        <div class="container py-3">
<?php if ($legalTitle !== '' || $taxNumber !== '' || $mersis !== ''): ?>
            <p>
<?= $legalTitle !== '' ? e($legalTitle) : '' ?>
<?= $taxOffice !== '' ? ' · Vergi Dairesi: ' . e($taxOffice) : '' ?>
<?= $taxNumber !== '' ? ' · Vergi No: ' . e($taxNumber) : '' ?>
<?= $mersis !== '' ? ' · Mersis No: ' . e($mersis) : '' ?>
            </p>
<?php endif; ?>
            <p class="mb-0">&copy; <?= e(date('Y')) ?> <?= e($siteName) ?>. Tüm hakları saklıdır. Fiyatlara KDV dahildir.</p>
        </div>
    </div>
</footer>
