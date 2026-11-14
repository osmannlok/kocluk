<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Helpers\Env;

Env::load(BASE_PATH . '/.env');

$appConfig = require BASE_PATH . '/config/app.php';

date_default_timezone_set($appConfig['timezone']);

if ($appConfig['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

session_name($appConfig['session']['name']);
session_set_cookie_params([
    'lifetime' => $appConfig['session']['lifetime'] * 60,
    'path' => '/',
    'domain' => '',
    'secure' => $appConfig['session']['secure'],
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

set_exception_handler(function (Throwable $e) use ($appConfig): void {
    http_response_code(500);
    if ($appConfig['debug']) {
        echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
    } else {
        require BASE_PATH . '/views/errors/500.php';
    }
});

$routes = require BASE_PATH . '/config/routes.php';

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$requestUri = rtrim($requestUri, '/');
if ($requestUri === '') {
    $requestUri = '/';
}
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$dispatcher = new App\Services\Router($routes);
$dispatcher->dispatch($requestMethod, $requestUri);