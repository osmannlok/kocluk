<?php // scripts/cancel_expired_orders.php
declare(strict_types=1);

/**
 * Süresi dolmuş pending_payment siparişlerini iptal eder (stok + kupon iadesi OrderStatusService içinde).
 * SADECE CLI. Cron (her 5 dakika):
 * * /5 * * * * /usr/bin/php /home/osman/Desktop/eticaret/scripts/cancel_expired_orders.php >> storage/logs/cron.log 2>&1
 */

use App\Core\Config;
use App\Core\Logger;
use App\Models\Order;
use App\Services\OrderStatusService;

// Web'den çağrılamaz (scripts zaten DocumentRoot dışındadır; ikinci savunma hattı).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
Config::load($base);

// Çakışma koruması: önceki çalışma bitmediyse yenisi hiçbir şey yapmadan çıkar.
$lockDir = $base . '/storage/cache';
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0750, true);
}
$lock = fopen($lockDir . '/cancel_expired_orders.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, date('Y-m-d H:i:s') . " önceki çalışma sürüyor, çıkılıyor.\n");
    exit(0);
}

$orders = new Order();
$statusService = new OrderStatusService();
$stats = ['bulunan' => 0, 'iptal' => 0, 'atlanan' => 0, 'hata' => 0];
$seen = [];

// Yığınlar halinde işlenir; aynı sipariş ikinci kez denenmez (sonsuz döngü koruması).
for ($round = 0; $round < 50; $round++) {
    $batch = $orders->expiredPending(now(), 100);
    $batch = array_values(array_filter($batch, static fn (array $o): bool => !isset($seen[(int) $o['id']])));
    if ($batch === []) {
        break;
    }

    foreach ($batch as $order) {
        $id = (int) $order['id'];
        $seen[$id] = true;
        $stats['bulunan']++;

        try {
            // Her sipariş kendi transaction'ında: biri hata verirse diğerleri etkilenmez.
            $statusService->transition($id, 'cancelled', null, 'Ödeme süresi doldu');
            $stats['iptal']++;
        } catch (DomainException $e) {
            // Bu arada ödenmiş/iptal edilmiş olabilir; sorun değil.
            $stats['atlanan']++;
        } catch (Throwable $e) {
            $stats['hata']++;
            Logger::error('Süresi dolan sipariş iptal edilemedi.', ['order_id' => $id, 'error' => $e->getMessage()]);
            fwrite(STDERR, date('Y-m-d H:i:s') . " HATA sipariş #{$id}: {$e->getMessage()}\n");
        }
    }
}

fwrite(STDOUT, sprintf(
    "%s bulunan=%d iptal=%d atlanan=%d hata=%d\n",
    date('Y-m-d H:i:s'),
    $stats['bulunan'],
    $stats['iptal'],
    $stats['atlanan'],
    $stats['hata']
));

flock($lock, LOCK_UN);
fclose($lock);
exit($stats['hata'] > 0 ? 1 : 0);