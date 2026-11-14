<?php
// scripts/paytr_fake_callback.php
// Yerel test: PayTR yerel Apache'ye ulaşamaz. Gerçek bildirim için tünel (cloudflared/ngrok) gerekir;
// bu betik doğru hash ile sahte bir bildirim göndererek akışı yerelde dener.
// Kullanım: php scripts/paytr_fake_callback.php <merchant_oid> <success|failed> <total_amount_kurus> [--bad-hash] [--repeat=2]

declare(strict_types=1);

use App\Core\Config;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
Config::load($base);

// Yalnızca yerel ortamda çalışır; üretimde gerçek anahtarlarla sahte bildirim üretilmesini engeller.
if ((string) Config::get('app.env', 'production') !== 'local') {
    fwrite(STDERR, "HATA: Bu betik yalnızca APP_ENV=local iken çalışır.\n");
    exit(1);
}

$positional = [];
$badHash = false;
$repeat = 1;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--bad-hash') {
        $badHash = true;
    } elseif (preg_match('/^--repeat=(\d+)$/', $arg, $m) === 1) {
        $repeat = max(1, min(5, (int) $m[1]));
    } elseif (str_starts_with($arg, '--')) {
        fwrite(STDERR, "Bilinmeyen seçenek: {$arg}\n");
        exit(2);
    } else {
        $positional[] = $arg;
    }
}

if (count($positional) !== 3) {
    fwrite(STDERR, "Kullanım: php scripts/paytr_fake_callback.php <merchant_oid> <success|failed> <total_amount_kurus> [--bad-hash] [--repeat=2]\n");
    exit(2);
}

[$merchantOid, $status, $totalAmount] = $positional;

if (preg_match('/^[A-Za-z0-9]+$/', $merchantOid) !== 1) {
    fwrite(STDERR, "HATA: merchant_oid yalnızca harf/rakam içermeli.\n");
    exit(2);
}
if (!in_array($status, ['success', 'failed'], true)) {
    fwrite(STDERR, "HATA: status success veya failed olmalı.\n");
    exit(2);
}
if (preg_match('/^\d{1,12}$/', $totalAmount) !== 1) {
    fwrite(STDERR, "HATA: total_amount kuruş cinsinden tamsayı olmalı (ör. 12500).\n");
    exit(2);
}

$merchantKey = (string) Config::get('paytr.merchant_key', '');
$merchantSalt = (string) Config::get('paytr.merchant_salt', '');
if ($merchantKey === '' || $merchantSalt === '') {
    fwrite(STDERR, "HATA: .env içinde PAYTR_MERCHANT_KEY / PAYTR_MERCHANT_SALT tanımlı değil.\n");
    exit(1);
}

// Doğrulama formülü: merchant_oid . salt . status . total_amount (anahtar/salt çıktıya yazılmaz).
$hash = $badHash
    ? base64_encode('bad-hash')
    : base64_encode(hash_hmac('sha256', $merchantOid . $merchantSalt . $status . $totalAmount, $merchantKey, true));

$fields = [
    'merchant_oid'  => $merchantOid,
    'status'        => $status,
    'total_amount'  => $totalAmount,
    'hash'          => $hash,
    'payment_type'  => 'card',
    'payment_amount' => $totalAmount,
    'currency'      => 'TL',
    'test_mode'     => '1',
];
if ($status === 'failed') {
    $fields['failed_reason_code'] = '0';
    $fields['failed_reason_msg'] = 'Sahte test bildirimi';
}

$url = rtrim((string) Config::get('app.url', ''), '/') . '/odeme/paytr/bildirim';
echo "Hedef: {$url}" . ($badHash ? ' (hatalı hash)' : '') . "\n";

$exit = 0;
for ($i = 1; $i <= $repeat; $i++) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    unset($ch);

    if ($body === false) {
        echo "#{$i} bağlantı hatası: {$err}\n";
        $exit = 1;
        continue;
    }

    echo "#{$i} HTTP {$code} -> " . json_encode((string) $body, JSON_UNESCAPED_UNICODE) . "\n";
    if ($code !== 200) {
        $exit = 1;
    }
}

exit($exit);