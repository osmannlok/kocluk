<?php
// app/Middleware/ThrottleMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\ErrorHandler;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

final class ThrottleMiddleware implements MiddlewareInterface
{
    public function handle(Request $r, callable $next, array $args = []): Response
    {
        $max = isset($args[0]) && ctype_digit((string) $args[0]) && (int) $args[0] > 0 ? (int) $args[0] : 60;
        $seconds = isset($args[1]) && ctype_digit((string) $args[1]) && (int) $args[1] > 0 ? (int) $args[1] : 60;

        $uid = Auth::id();
        $key = 'throttle:' . $r->path() . '|' . $r->ip() . ($uid !== null ? '|u' . $uid : '');

        if (RateLimiter::tooManyAttempts($key, $max, $seconds)) {
            $retry = max(1, RateLimiter::availableIn($key));
            return ErrorHandler::handle(
                new HttpException(429, 'Çok fazla istek gönderdiniz. Lütfen ' . $retry . ' saniye sonra tekrar deneyin.'),
                $r
            )->withHeader('Retry-After', (string) $retry);
        }

        RateLimiter::hit($key, $seconds);
        return $next($r);
    }
}