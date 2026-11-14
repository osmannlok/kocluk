<?php
// app/Middleware/SecurityHeadersMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $r, callable $next, array $args = []): Response
    {
        // En dış katman: hata sayfaları da güvenlik başlıklarını alsın diye istisnalar burada yanıta çevrilir.
        try {
            $response = $next($r);
        } catch (Throwable $e) {
            $response = ErrorHandler::handle($e, $r);
        }

        $response->withHeader('Content-Security-Policy', $this->buildCsp())
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ((bool) Config::get('security.hsts', false)) {
            $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (!headers_sent()) {
            header_remove('X-Powered-By');
            header_remove('Server');
        }
        return $response;
    }

    private function buildCsp(): string
    {
        $parts = [];
        foreach ((array) Config::get('security.csp', []) as $directive => $sources) {
            $directive = preg_replace('/[^a-z0-9\-]/i', '', (string) $directive);
            if ($directive === '' || $directive === null) {
                continue;
            }
            $list = array_filter(array_map('strval', (array) $sources), static fn (string $s): bool => $s !== '');
            $parts[] = trim($directive . ' ' . implode(' ', $list));
        }
        // Başlık enjeksiyonuna karşı satır sonları temizlenir.
        return (string) preg_replace('/[\r\n]+/', ' ', implode('; ', $parts));
    }
}