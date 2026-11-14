<?php
// public/index.php
declare(strict_types=1);

use App\Core\Config;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

$basePath = dirname(__DIR__);

try {
    require $basePath . '/vendor/autoload.php';

    Config::load($basePath);
    date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Istanbul'));

    // Hata ayrıntısı yalnızca debug'da ekrana gider; her durumda loga yazılır.
    $debug = (bool) Config::get('app.debug', false);
    error_reporting(E_ALL);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');
    ini_set('error_log', Config::basePath('storage/logs/php-error.log'));

    ErrorHandler::register();
    Session::start();

    $request = Request::capture();
    $router = new Router();

    (static function (Router $router, string $basePath): void {
        require $basePath . '/routes/web.php';
        if (is_file($basePath . '/routes/admin.php')) {
            require $basePath . '/routes/admin.php';
        }
    })($router, $basePath);

    try {
        $response = $router->dispatch($request);
    } catch (Throwable $e) {
        $response = ErrorHandler::handle($e, $request);
    }
    $response->send();
} catch (Throwable $fatal) {
    // Başlatma aşamasında (config/DB/oturum) çöküş: ayrıntı sızdırmadan genel yanıt.
    error_log('Başlatma hatası: ' . get_class($fatal) . ' ' . $fatal->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo 'Sunucu hatası. Lütfen daha sonra tekrar deneyin.';
}