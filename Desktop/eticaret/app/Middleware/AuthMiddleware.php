<?php
// app/Middleware/AuthMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use stdClass;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $r, callable $next, array $args = []): Response
    {
        if (Auth::check()) {
            return $next($r);
        }

        if ($r->wantsJson() || $r->isAjax()) {
            return Response::json([
                'ok' => false,
                'error' => [
                    'code' => 'unauthenticated',
                    'message' => 'Bu işlem için oturum açmanız gerekiyor.',
                    'errors' => new stdClass(),
                ],
            ], 401);
        }

        if (strtoupper($r->method()) === 'GET') {
            $query = $r->query();
            $target = $r->path() . (is_array($query) && $query !== [] ? '?' . http_build_query($query) : '');
            $safe = $this->safeRelative($target);
            if ($safe !== null) {
                Session::put('intended_url', $safe);
            }
        }

        flash('warning', 'Devam etmek için lütfen giriş yapın.');
        return Response::redirect('/giris');
    }

    // Açık yönlendirme koruması: yalnızca aynı site göreli yolu saklanır.
    private function safeRelative(string $p): ?string
    {
        if ($p === '' || $p[0] !== '/' || str_starts_with($p, '//') || str_contains($p, '\\')
            || strlen($p) > 255 || preg_match('/[\x00-\x1F\x7F]/', $p) === 1) {
            return null;
        }
        return $p;
    }
}