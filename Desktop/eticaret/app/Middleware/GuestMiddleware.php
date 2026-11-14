<?php
// app/Middleware/GuestMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $r, callable $next, array $args = []): Response
    {
        if (!Auth::check()) {
            return $next($r);
        }
        return Response::redirect(Auth::can('dashboard.view') ? '/yonetim' : '/hesabim');
    }
}