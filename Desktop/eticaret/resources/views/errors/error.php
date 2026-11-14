<?php
declare(strict_types=1);

// resources/views/errors/error.php
// Girdi: $code, $title, $message. ErrorHandler önce layouts.main ile, olmazsa layoutsuz çağırır.
// Layoutsuz çağrıda (veya $standalone=true ise) bağımsız, tam HTML sayfa üretir.

$code = isset($code) ? (int) $code : 500;

// Marka tonlu metinler; ayrıntı/istisna mesajı asla basılmaz (bilgi sızıntısı olmasın).
$texts = [
    404 => ['Aradığınız sayfa bulunamadı', 'Bu adres taşınmış ya da kaldırılmış olabilir. Ana sayfadan devam edebilir veya aradığınız ürünü arayabilirsiniz.'],
    403 => ['Bu sayfaya erişim izniniz yok', 'Bu bölümü görüntülemek için gerekli yetkiye sahip değilsiniz. Yanlışlık olduğunu düşünüyorsanız bizimle iletişime geçin.'],
    419 => ['Oturumunuzun süresi doldu', 'Güvenliğiniz için işlem zaman aşımına uğradı. Lütfen sayfayı yenileyip tekrar deneyin.'],
    429 => ['Çok fazla istek gönderdiniz', 'Kısa bir süre içinde çok sayıda istek aldık. Lütfen biraz bekleyip tekrar deneyin.'],
    500 => ['Bir şeyler ters gitti', 'Sizi bekletmek istemezdik. Sorun kaydedildi; lütfen biraz sonra tekrar deneyin.'],
];

if (isset($texts[$code])) {
    [$heading, $text] = $texts[$code];
} else {
    $heading = isset($title) && is_string($title) && $title !== '' ? $title : 'Bir sorun oluştu';
    $text = isset($message) && is_string($message) && $message !== '' && $code < 500
        ? $message
        : 'İsteğiniz tamamlanamadı. Lütfen daha sonra tekrar deneyin.';
}

// Layout içinde mi çalışıyoruz? View::render'ın layout argümanına bakılır (null ise bağımsız sayfa).
if (!isset($standalone)) {
    $standalone = true;
    foreach (debug_backtrace(0, 10) as $frame) {
        if (($frame['class'] ?? '') === 'App\\Core\\View' && ($frame['function'] ?? '') === 'render') {
            $standalone = empty($frame['args'][2]);
            break;
        }
    }
}

$homeUrl = function_exists('url') ? url('/') : '/';
$searchUrl = function_exists('url') ? url('/ara') : '/ara';
$cssBootstrap = function_exists('asset') ? asset('vendor/bootstrap/css/bootstrap.min.css') : '/assets/vendor/bootstrap/css/bootstrap.min.css';
$cssApp = function_exists('asset') ? asset('css/app.css') : '/assets/css/app.css';
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php if ($standalone): ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $h($heading) ?> | Yöresel Market</title>
    <link rel="stylesheet" href="<?= $h($cssBootstrap) ?>">
    <link rel="stylesheet" href="<?= $h($cssApp) ?>">
</head>
<body class="ym-body">
<main id="main">
<?php endif; ?>
    <section class="ym-error container" aria-labelledby="ymErrorTitle">
        <p class="ym-error__code" aria-hidden="true"><?= $h((string) $code) ?></p>
        <h1 id="ymErrorTitle" class="h3 mt-2"><?= $h($heading) ?></h1>
        <p class="text-muted"><?= $h($text) ?></p>
        <div class="ym-error__actions">
            <a class="btn btn-primary" href="<?= $h($homeUrl) ?>">Ana sayfaya dön</a>
            <a class="btn btn-outline-primary" href="<?= $h($searchUrl) ?>">Ürün ara</a>
        </div>
    </section>
<?php if ($standalone): ?>
</main>
</body>
</html>
<?php endif; ?>
