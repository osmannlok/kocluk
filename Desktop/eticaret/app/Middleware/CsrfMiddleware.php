<?php
// app/Middleware/CsrfMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\SecurityEvent;
use stdClass;
use Throwable;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const MESSAGE = 'Oturum süreniz doldu veya istek doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';

    public function handle(Request $r, callable $next, array $args = []): Response
    {
        if (in_array(strtoupper($r->method()), self::SAFE_METHODS, true)) {
            return $next($r);
        }

        // Tek istisna (ör. PayTR bildirimi) tam yol eşleşmesiyle; o uçta başka doğrulama (hash) zorunludur.
        $path = '/' . trim($r->path(), '/');
        foreach ((array) Config::get('security.csrf_except', []) as $except) {
            if ($path === '/' . trim((string) $except, '/')) {
                return $next($r);
            }
        }

        $token = $r->header('X-CSRF-Token');
        if ($token === null || $token === '') {
            $in = $r->input('_token');
            $token = is_string($in) ? $in : null;
        }

        if (Csrf::validate($token)) {
            return $next($r);
        }

        try {
            SecurityEvent::record('csrf_fail', 'warning', Auth::id(), $r->ip(), [
                'path' => $path,
                'method' => $r->method(),
            ]);
        } catch (Throwable) {
            // olay yazımı isteği düşürmez
        }

        if ($r->wantsJson() || $r->isAjax()) {
            return Response::json([
                'ok' => false,
                'error' => ['code' => 'csrf', 'message' => self::MESSAGE, 'errors' => new stdClass()],
            ], 419);
        }
        throw new HttpException(419, self::MESSAGE);
    }
}