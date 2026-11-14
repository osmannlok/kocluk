<?php
// app/Middleware/PermissionMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\SecurityEvent;
use stdClass;
use Throwable;

final class PermissionMiddleware implements MiddlewareInterface
{
    public function handle(Request $r, callable $next, array $args = []): Response
    {
        $json = $r->wantsJson() || $r->isAjax();

        if (!Auth::check()) {
            return $this->deny($json, 401, 'unauthenticated', 'Bu işlem için oturum açmanız gerekiyor.');
        }

        // 'a,b' = herhangi biri; Router virgülle böldüyse de, tek parça gelirse de aynı sonuç.
        $codes = array_values(array_filter(array_map('trim', explode(',', implode(',', $args))), 'strlen'));

        // Varsayılan: reddet. Kod verilmemişse hiç kimse geçemez.
        foreach ($codes as $code) {
            if (Auth::can($code)) {
                return $next($r);
            }
        }

        try {
            SecurityEvent::record('permission_denied', 'warning', Auth::id(), $r->ip(), [
                'permission' => implode(',', $codes),
                'path' => $r->path(),
                'method' => $r->method(),
            ]);
        } catch (Throwable) {
            // olay yazımı isteği düşürmez
        }

        return $this->deny($json, 403, 'forbidden', 'Bu işlem için yetkiniz bulunmuyor.');
    }

    private function deny(bool $json, int $status, string $code, string $message): Response
    {
        if ($json) {
            return Response::json([
                'ok' => false,
                'error' => ['code' => $code, 'message' => $message, 'errors' => new stdClass()],
            ], $status);
        }
        throw new HttpException($status, $message);
    }
}