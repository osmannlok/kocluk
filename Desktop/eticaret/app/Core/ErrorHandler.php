<?php
// app/Core/ErrorHandler.php
declare(strict_types=1);

namespace App\Core;

use ErrorException;
use stdClass;
use Throwable;

final class ErrorHandler
{
    private const TITLES = [
        400 => 'Hatalı İstek',
        401 => 'Oturum Gerekli',
        403 => 'Erişim Reddedildi',
        404 => 'Sayfa Bulunamadı',
        405 => 'Yöntem İzin Verilmiyor',
        419 => 'Oturum Süresi Doldu',
        422 => 'Geçersiz Bilgi',
        429 => 'Çok Fazla İstek',
        500 => 'Sunucu Hatası',
    ];

    private const MESSAGES = [
        400 => 'İsteğiniz anlaşılamadı.',
        401 => 'Bu işlem için oturum açmanız gerekiyor.',
        403 => 'Bu işlem için yetkiniz bulunmuyor.',
        404 => 'Aradığınız sayfa bulunamadı.',
        405 => 'Bu istek yöntemi bu adres için desteklenmiyor.',
        419 => 'Oturum süreniz doldu veya istek doğrulanamadı. Sayfayı yenileyip tekrar deneyin.',
        422 => 'Girilen bilgiler geçersiz.',
        429 => 'Çok fazla istek gönderdiniz. Lütfen biraz bekleyip tekrar deneyin.',
        500 => 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.',
    ];

    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        419 => 'csrf',
        422 => 'validation',
        429 => 'rate_limited',
        500 => 'server_error',
    ];

    public static function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0) {
                Logger::warning('Kullanımdan kalkmış özellik: ' . $message, ['file' => basename($file), 'line' => $line]);
                return true;
            }
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            try {
                self::handle($e, Request::capture())->send();
            } catch (Throwable $inner) {
                error_log('ErrorHandler çöktü: ' . get_class($inner) . ' ' . $inner->getMessage());
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: text/plain; charset=UTF-8');
                }
                echo 'Sunucu hatası. Lütfen daha sonra tekrar deneyin.';
            }
        });

        register_shutdown_function(static function (): void {
            $err = error_get_last();
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR];
            if ($err === null || !in_array($err['type'], $fatal, true)) {
                return;
            }
            Logger::error('Ölümcül hata', [
                'message' => $err['message'],
                'file' => basename($err['file']),
                'line' => $err['line'],
            ]);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
            }
            echo 'Sunucu hatası. Lütfen daha sonra tekrar deneyin.';
        });
    }

    public static function handle(Throwable $e, Request $r): Response
    {
        $json = $r->wantsJson() || $r->isAjax();

        if ($e instanceof ValidationException) {
            return self::validationResponse($e, $r, $json);
        }

        if ($e instanceof HttpException) {
            $status = $e->getStatus();
            $message = $e->getMessage() !== '' ? $e->getMessage() : (self::MESSAGES[$status] ?? 'Bir hata oluştu.');
            if ($status >= 500) {
                Logger::error('HTTP hatası', self::context($e));
            }
            return self::render($status, $message, $json);
        }

        Logger::error('Yakalanmamış istisna', self::context($e));
        // Ayrıntı yalnızca APP_DEBUG=true iken gösterilir; üretimde genel mesaj döner.
        $message = (bool) Config::get('app.debug', false)
            ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
            : self::MESSAGES[500];
        return self::render(500, $message, $json);
    }

    private static function render(int $status, string $message, bool $json): Response
    {
        if ($json) {
            return Response::json([
                'ok' => false,
                'error' => [
                    'code' => self::CODES[$status] ?? 'error',
                    'message' => $message,
                    'errors' => new stdClass(),
                ],
            ], $status);
        }

        $data = ['code' => $status, 'title' => self::TITLES[$status] ?? 'Hata', 'message' => $message];
        foreach ([['errors.error', 'layouts.main'], ['errors.error', null]] as [$view, $layout]) {
            try {
                return Response::html(View::render($view, $data, $layout), $status);
            } catch (Throwable) {
                // view/layout yoksa veya hata verirse bir sonraki yedeğe geç
            }
        }
        return Response::text($status . ' - ' . $data['title'] . ': ' . $message, $status);
    }

    private static function validationResponse(ValidationException $e, Request $r, bool $json): Response
    {
        if ($json) {
            return Response::json([
                'ok' => false,
                'error' => [
                    'code' => 'validation',
                    'message' => self::MESSAGES[422],
                    'errors' => $e->errors() === [] ? new stdClass() : $e->errors(),
                ],
            ], 422);
        }

        try {
            Session::flash('errors', $e->errors());
            Session::flash('old', $e->old());
        } catch (Throwable) {
            // oturum yoksa yönlendirme yine yapılır
        }
        return Response::redirect(self::backUrl($r), 303);
    }

    // Açık yönlendirme yok: yalnızca aynı hosttan gelen Referer'ın göreli yolu kullanılır.
    private static function backUrl(Request $r): string
    {
        $ref = $r->header('Referer');
        if ($ref === null || $ref === '') {
            return '/';
        }
        $parts = parse_url($ref);
        $host = strtolower((string) $r->header('Host'));
        if ($parts === false || !isset($parts['host'])) {
            return '/';
        }
        $refHost = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        if ($host === '' || $refHost !== $host) {
            return '/';
        }
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return '/';
        }
        $url = $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
        return preg_match('/[\x00-\x1F\x7F]/', $url) === 1 ? '/' : $url;
    }

    /** @return array<string,mixed> */
    private static function context(Throwable $e): array
    {
        $frames = [];
        foreach (array_slice($e->getTrace(), 0, 8) as $f) {
            $where = isset($f['file']) ? basename($f['file']) . ':' . ($f['line'] ?? 0) : 'internal';
            $frames[] = $where . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '');
        }
        return [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $frames,
        ];
    }
}