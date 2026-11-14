<?php
declare(strict_types=1);

// resources/views/layouts/main.php
// Ana vitrin düzeni. Değişkenler: $title, $metaDescription, $canonical, $content, $bodyClass.

use App\Core\View;
use App\Models\Setting;

// Ayarlar yalnızca is_public=1 olanlarla alınır; gizli ayarlar (IBAN vb.) vitrine sızmaz.
if (!isset($settings) || !is_array($settings)) {
    $settings = (new Setting())->publicAll();
}
View::share('settings', $settings);

$siteName = (string) ($settings['site.name'] ?? 'Yöresel Market');
$pageTitle = isset($title) && is_string($title) && $title !== ''
    ? $title
    : (string) ($settings['seo.default_title'] ?? $siteName);
$fullTitle = ($pageTitle === $siteName || str_contains($pageTitle, $siteName)) ? $pageTitle : $pageTitle . ' | ' . $siteName;
$description = isset($metaDescription) && is_string($metaDescription) && $metaDescription !== ''
    ? $metaDescription
    : (string) ($settings['seo.default_description'] ?? '');

// Kanonik adres: parametresiz yol (sorgu dizesi kanonik adrese girmez).
$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($requestPath === '' || $requestPath[0] !== '/') {
    $requestPath = '/';
}
$canonicalUrl = isset($canonical) && is_string($canonical) && $canonical !== '' ? $canonical : url($requestPath);

$bodyCss = 'ym-body' . (isset($bodyClass) && is_string($bodyClass) && $bodyClass !== '' ? ' ' . $bodyClass : '');
$partialData = ['settings' => $settings];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#2f5d3a">
    <title><?= e($fullTitle) ?></title>
<?php if ($description !== ''): ?>
    <meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
<?php if ($description !== ''): ?>
    <meta property="og:description" content="<?= e($description) ?>">
<?php endif; ?>
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php if (isset($ogImage) && is_string($ogImage) && $ogImage !== ''): ?>
    <meta property="og:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php foreach ((is_array($styles ?? null) ? $styles : []) as $ymStyle): ?>
        <?php // Neden regex: yalnızca public/assets/css altındaki düz dosya adları basılır (yol atlatma yok). ?>
        <?php if (is_string($ymStyle) && preg_match('/^[a-z0-9._-]+\.(css)$/', $ymStyle) === 1): ?>
        <link rel="stylesheet" href="<?= e(asset('css/' . $ymStyle)) ?>">
        <?php endif; ?>
    <?php endforeach; ?>

    <?php /* BLOK 2: </body> öncesinde, app.js ve cookie-consent.js <script> satırlarından SONRA ekle */ ?>
    <?php foreach ((is_array($scripts ?? null) ? $scripts : []) as $ymScript): ?>
        <?php if (is_string($ymScript) && preg_match('/^[a-z0-9._-]+\.(js)$/', $ymScript) === 1): ?>
        <script src="<?= e(asset('js/' . $ymScript)) ?>" defer></script>
        <?php endif; ?>
    <?php endforeach; ?>
</head>
<body class="<?= e($bodyCss) ?>">
    <a class="ym-skip" href="#main">İçeriğe atla</a>

    <!-- SVG simge sprite'ı: CSP uyumlu, dış bağımlılık yok -->
    <svg width="0" height="0" class="position-absolute" aria-hidden="true" focusable="false">
        <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></symbol>
        <symbol id="i-cart" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></symbol>
        <symbol id="i-user" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
        <symbol id="i-menu" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></symbol>
        <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></symbol>
        <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></symbol>
        <symbol id="i-x" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
        <symbol id="i-chevron-down" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></symbol>
        <symbol id="i-chevron-right" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></symbol>
        <symbol id="i-chevron-left" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></symbol>
        <symbol id="i-truck" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></symbol>
        <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></symbol>
        <symbol id="i-pin" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></symbol>
        <symbol id="i-phone" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></symbol>
        <symbol id="i-mail" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></symbol>
        <symbol id="i-instagram" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></symbol>
        <symbol id="i-facebook" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></symbol>
        <symbol id="i-whatsapp" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></symbol>
        <symbol id="i-package" viewBox="0 0 24 24"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></symbol>
        <symbol id="i-leaf" viewBox="0 0 24 24"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></symbol>
        <symbol id="i-arrow-right" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></symbol>
    </svg>

<?= View::partial('partials.announcement', $partialData) ?>
<?= View::partial('partials.header', $partialData) ?>
<?= View::partial('partials.flash', $partialData) ?>

    <main id="main" class="ym-main" tabindex="-1">
<?= $content ?? '' ?>
    </main>

<?= View::partial('partials.footer', $partialData) ?>
<?= View::partial('partials.mobile-nav', $partialData) ?>
<?= View::partial('partials.cookie-banner', $partialData) ?>

    <script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>" defer></script>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <script src="<?= e(asset('js/cookie-consent.js')) ?>" defer></script>
</body>
</html>
