<?php // app/Core/Response.php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * HTTP yanıtı. Üreticiler (html/json/redirect/text) nesne döndürür; send() çıktıyı yazar.
 */
class Response
{
    /** @var array<string, array{0: string, 1: string}> küçük harf ad => [ad, değer] */
    private array $headers = [];

    private function __construct(private string $body, private int $status)
    {
    }

    public static function html(string $b, int $s = 200): self
    {
        return (new self($b, $s))->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** @param array<string|int, mixed> $d */
    public static function json(array $d, int $s = 200): self
    {
        $body = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        return (new self($body, $s))
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            // Kişisel/CSRF verisi içerebilen JSON ara belleklerde saklanmasın
            ->withHeader('Cache-Control', 'no-store');
    }

    public static function text(string $b, int $s = 200): self
    {
        return (new self($b, $s))->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public static function redirect(string $url, int $s = 302): self
    {
        if (!in_array($s, [301, 302, 303, 307, 308], true)) {
            $s = 302;
        }
        return (new self('', $s))->withHeader('Location', self::safeLocation($url));
    }

    /** Aynı nesneyi döndürür (zincirleme). Başlık enjeksiyonuna (CRLF) karşı doğrulanır. */
    public function withHeader(string $k, string $v): self
    {
        if (preg_match('/^[A-Za-z0-9-]+$/D', $k) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $v) === 1) {
            throw new InvalidArgumentException('Geçersiz HTTP başlığı.');
        }
        $this->headers[strtolower($k)] = [$k, $v];
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as [$name, $value]) {
                header($name . ': ' . $value, true);
            }
        }
        if (!in_array($this->status, [204, 304], true)) {
            echo $this->body;
        }
    }

    /**
     * Açık yönlendirme koruması: yalnızca "/" ile başlayan göreli yol veya APP_URL ile aynı host; aksi halde "/".
     */
    private static function safeLocation(string $url): string
    {
        // Boşluk/kontrol karakteri ve ters eğik çizgi (tarayıcılar "/\evil.com"u "//evil.com" sayar) reddedilir
        if ($url === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return '/';
        }
        if ($url[0] === '/') {
            // "//evil.com" protokol-göreli adres dış siteye gider
            return ($url[1] ?? '') === '/' ? '/' : $url;
        }

        $parts = parse_url($url);
        if (
            $parts === false || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            return '/';
        }

        // Host, istekteki Host başlığına değil yapılandırmadaki APP_URL'ye göre karşılaştırılır (başlık taklit edilebilir)
        $appHost = parse_url((string) Config::get('app.url', ''), PHP_URL_HOST);
        if (!is_string($appHost) || strcasecmp($appHost, $parts['host']) !== 0) {
            return '/';
        }
        return $url;
    }
}