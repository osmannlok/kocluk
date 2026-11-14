<?php // app/Core/Request.php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP isteği. Girdi trim'lenmez (ham); doğrulama Validator'da yapılır.
 */
class Request
{
    private string $method;
    private string $path;

    /** @var array<string, mixed> */
    private array $routeParams = [];

    /** @var array<string, mixed>|null */
    private ?array $jsonCache = null;

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $postParams
     * @param array<string, mixed> $files
     */
    public function __construct(
        private array $server,
        private array $queryParams,
        private array $postParams,
        private array $files,
        private ?string $rawBody = null
    ) {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        // _method yalnızca POST'ta ve yalnızca PUT/DELETE için kabul edilir (HTML formu taklidi)
        if ($method === 'POST') {
            $override = $postParams['_method'] ?? null;
            if (is_string($override) && in_array(strtoupper($override), ['PUT', 'DELETE'], true)) {
                $method = strtoupper($override);
            }
        }
        $this->method = $method;
        $this->path = self::normalizePath((string) ($server['REQUEST_URI'] ?? '/'));
    }

    public static function capture(): self
    {
        return new self($_SERVER, $_GET, $_POST, $_FILES);
    }

    public function method(): string
    {
        return $this->method;
    }

    /** Sorgu dizesiz, çözülmüş, normalize yol: "/urun/x" (sondaki / atılır, kök "/"). */
    public function path(): string
    {
        return $this->path;
    }

    /** Sorgu + gövde (POST ve JSON) birleşimi; gövde sorguyu ezer. */
    public function input(string $k, mixed $d = null): mixed
    {
        $all = $this->all();
        return array_key_exists($k, $all) ? $all[$k] : $d;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->queryParams, $this->postParams, $this->json());
    }

    public function query(?string $k = null, mixed $d = null): mixed
    {
        if ($k === null) {
            return $this->queryParams;
        }
        return array_key_exists($k, $this->queryParams) ? $this->queryParams[$k] : $d;
    }

    public function post(?string $k = null, mixed $d = null): mixed
    {
        $body = array_merge($this->postParams, $this->json());
        if ($k === null) {
            return $body;
        }
        return array_key_exists($k, $body) ? $body[$k] : $d;
    }

    /** @return array<string, mixed> Geçersiz veya JSON olmayan gövde => boş dizi. */
    public function json(): array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }
        $this->jsonCache = [];

        $type = strtolower((string) $this->header('Content-Type'));
        if (!str_contains($type, 'json')) {
            return $this->jsonCache;
        }

        $raw = $this->rawBody ?? (string) file_get_contents('php://input');
        if ($raw === '') {
            return $this->jsonCache;
        }
        $decoded = json_decode($raw, true, 32);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $this->jsonCache = $decoded;
        }
        return $this->jsonCache;
    }

    /** @return array<string, mixed>|null Yüklenen dosya (yoksa/çoklu ise null). */
    public function file(string $k): ?array
    {
        $file = $this->files[$k] ?? null;
        if (!is_array($file) || is_array($file['name'] ?? null)) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    /**
     * İstemci IP'si. REMOTE_ADDR güvenilir proxy listesindeyse X-Forwarded-For'un ilk geçerli IP'si,
     * değilse REMOTE_ADDR (aksi halde istemci başlığı taklit ederek IP kilidini/hız sınırını aşardı).
     */
    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '');
        $remote = filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '0.0.0.0';

        $trusted = Config::get('app.trusted_proxies', []);
        if (is_array($trusted) && $trusted !== [] && $this->isTrustedProxy($remote, $trusted)) {
            $forwarded = (string) ($this->server['HTTP_X_FORWARDED_FOR'] ?? '');
            foreach (explode(',', $forwarded) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }
        return $remote;
    }

    public function userAgent(): string
    {
        // Denetim kayıtları için kontrol karakterleri atılır, 255'e kırpılır (DB sütunu V255)
        $ua = (string) preg_replace('/[\x00-\x1F\x7F]/', '', (string) ($this->server['HTTP_USER_AGENT'] ?? ''));
        return substr($ua, 0, 255);
    }

    public function isAjax(): bool
    {
        return strcasecmp((string) $this->header('X-Requested-With'), 'XMLHttpRequest') === 0;
    }

    public function wantsJson(): bool
    {
        return $this->isAjax()
            || str_contains(strtolower((string) $this->header('Accept')), 'application/json')
            || str_contains(strtolower((string) $this->header('Content-Type')), 'json');
    }

    public function header(string $n): ?string
    {
        $key = strtoupper(str_replace('-', '_', $n));
        $serverKey = in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_' . $key;
        $value = $this->server[$serverKey] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function routeParam(string $k, mixed $d = null): mixed
    {
        return array_key_exists($k, $this->routeParams) ? $this->routeParams[$k] : $d;
    }

    /** @param array<string, mixed> $p */
    public function setRouteParams(array $p): void
    {
        $this->routeParams = $p;
    }

    /** @param array<int, mixed> $trusted */
    private function isTrustedProxy(string $remote, array $trusted): bool
    {
        $remoteBin = @inet_pton($remote);
        if ($remoteBin === false) {
            return false;
        }
        foreach ($trusted as $proxy) {
            $proxyBin = is_string($proxy) ? @inet_pton(trim($proxy)) : false;
            if ($proxyBin !== false && hash_equals($proxyBin, $remoteBin)) {
                return true;
            }
        }
        return false;
    }

    private static function normalizePath(string $uri): string
    {
        $path = explode('?', $uri, 2)[0];
        $path = explode('#', $path, 2)[0];
        $path = (string) preg_replace('/[\x00-\x1F\x7F]/', '', rawurldecode($path));
        $path = (string) preg_replace('#/+#', '/', '/' . $path);
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}