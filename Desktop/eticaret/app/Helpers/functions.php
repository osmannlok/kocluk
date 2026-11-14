<?php // app/Helpers/functions.php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Money;

// Global yardımcılar. Hepsi function_exists korumalıdır (iki kez yüklenirse çakışmaz).

if (!function_exists('env')) {
    /** .env değeri (true/false/null/tamsayı dönüşümlü). */
    function env(string $key, mixed $default = null): mixed
    {
        return Config::env($key, $default);
    }
}

if (!function_exists('config')) {
    /** Noktalı yapılandırma anahtarı: config('app.name'). */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    /** XSS koruması: HTML çıktı kaçışı (null-güvenli). */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_scalar($value) && !($value instanceof Stringable)) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Site adresi + yol: url('/urunler'). */
    function url(string $path = ''): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /** public/assets altındaki dosya URL'si; dosya değişince önbellek ?v= ile tazelenir. */
    function asset(string $path): string
    {
        $clean = ltrim(str_replace(['..', '\\'], '', $path), '/');
        $file = Config::basePath('public/assets/' . $clean);
        $version = is_file($file) ? '?v=' . (string) filemtime($file) : '';
        return url('assets/' . $clean) . $version;
    }
}

if (!function_exists('route')) {
    /** Yol kalıbından URL üretir: route('/urun/{slug}', ['slug' => 'x']); artan parametreler sorgu dizesine gider. */
    function route(string $path, array $params = []): string
    {
        $used = [];
        $built = preg_replace_callback(
            '/\{(\w+)(?::[^}]+)?\}/',
            static function (array $m) use ($params, &$used): string {
                if (!array_key_exists($m[1], $params)) {
                    throw new InvalidArgumentException('Eksik rota parametresi: ' . $m[1]);
                }
                $used[$m[1]] = true;
                return rawurlencode((string) $params[$m[1]]);
            },
            $path
        );
        $rest = array_diff_key($params, $used);
        $query = $rest !== [] ? '?' . http_build_query($rest, '', '&', PHP_QUERY_RFC3986) : '';
        return url((string) $built . $query);
    }
}

if (!function_exists('redirect')) {
    /** Yönlendirme yanıtı döndürür (açık yönlendirme Response içinde engellenir). */
    function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }
}

if (!function_exists('back')) {
    /** Referer aynı siteye aitse oraya, değilse yedek yola döner (açık yönlendirme yok). */
    function back(string $fallback = '/'): Response
    {
        $target = $fallback;
        $referer = Request::capture()->header('Referer');
        if ($referer !== null) {
            $parts = parse_url($referer);
            $appHost = parse_url((string) Config::get('app.url', ''), PHP_URL_HOST);
            if (
                is_array($parts) && isset($parts['host']) && is_string($appHost)
                && strcasecmp($parts['host'], $appHost) === 0
                && !isset($parts['user'])
            ) {
                $target = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
            }
        }
        return Response::redirect($target);
    }
}

if (!function_exists('old')) {
    /** Doğrulama hatasında geri doldurulan eski girdi (flash; istek başına bir kez okunur). */
    function old(string $key, mixed $default = null): mixed
    {
        static $old = null;
        if ($old === null) {
            $flashed = Session::pullFlash('old', []);
            $old = is_array($flashed) ? $flashed : [];
        }
        return array_key_exists($key, $old) ? $old[$key] : $default;
    }
}

if (!function_exists('errors')) {
    /** Doğrulama hataları: errors() tümü; errors('email') ilk mesaj veya null. */
    function errors(?string $key = null): array|string|null
    {
        static $errors = null;
        if ($errors === null) {
            $flashed = Session::pullFlash('errors', []);
            $errors = is_array($flashed) ? $flashed : [];
        }
        if ($key === null) {
            return $errors;
        }
        if (!isset($errors[$key])) {
            return null;
        }
        $value = $errors[$key];
        if (is_array($value)) {
            $value = reset($value);
        }
        return is_string($value) ? $value : null;
    }
}

if (!function_exists('flash')) {
    /** Oturumdaki bildirim listesine ekler (success|danger|warning|info). */
    function flash(string $type, string $message): void
    {
        $type = $type === 'error' ? 'danger' : $type;
        if (!in_array($type, ['success', 'danger', 'warning', 'info'], true)) {
            $type = 'info';
        }
        $list = Session::get('_flash_messages', []);
        $list = is_array($list) ? $list : [];
        $list[] = ['type' => $type, 'message' => $message];
        Session::put('_flash_messages', $list);
    }
}

if (!function_exists('flash_messages')) {
    /** @return array<int, array{type: string, message: string}> Okur ve temizler. */
    function flash_messages(): array
    {
        $list = Session::get('_flash_messages', []);
        Session::forget('_flash_messages');
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('method_field')) {
    /** HTML formları için _method gizli alanı (yalnızca PUT/DELETE). */
    function method_field(string $method): string
    {
        $method = strtoupper($method);
        if (!in_array($method, ['PUT', 'DELETE'], true)) {
            throw new InvalidArgumentException('Geçersiz yöntem.');
        }
        return '<input type="hidden" name="_method" value="' . $method . '">';
    }
}

if (!function_exists('auth')) {
    /** Oturumdaki kullanıcı (yoksa null). */
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('can')) {
    /** Permission kodu kontrolü: can('product.create'). */
    function can(string $permission): bool
    {
        return Auth::can($permission);
    }
}

if (!function_exists('money')) {
    /** "1234.5" -> "1.234,50 ₺" */
    function money(string|int|float $amount): string
    {
        if (is_float($amount)) {
            $amount = sprintf('%.2F', $amount);
        }
        return Money::format((string) $amount);
    }
}

if (!function_exists('slugify')) {
    /** Türkçe karakterli metinden URL slug'ı üretir. */
    function slugify(string $text): string
    {
        $text = strtr($text, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ç' => 'c', 'ç' => 'c', 'Ğ' => 'g', 'ğ' => 'g',
            'Ö' => 'o', 'ö' => 'o', 'Ş' => 's', 'ş' => 's', 'Ü' => 'u', 'ü' => 'u',
            'Â' => 'a', 'â' => 'a', 'Î' => 'i', 'î' => 'i', 'Û' => 'u', 'û' => 'u',
        ]);
        $text = mb_strtolower($text, 'UTF-8');
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_D);
            if (is_string($normalized)) {
                $text = (string) preg_replace('/\p{Mn}+/u', '', $normalized);
            }
        }
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}

if (!function_exists('now')) {
    /** Şu an (uygulama saat diliminde) "Y-m-d H:i:s". */
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('ip_to_bin')) {
    /** IPv4/IPv6 -> VARBINARY(16) için ikili değer; geçersizse null. */
    function ip_to_bin(string $ip): ?string
    {
        $bin = @inet_pton($ip);
        return $bin === false ? null : $bin;
    }
}

if (!function_exists('bin_to_ip')) {
    /** VARBINARY(16) -> okunur IP; boş/geçersizse ''. */
    function bin_to_ip(?string $bin): string
    {
        if ($bin === null || ($bin !== '' && !in_array(strlen($bin), [4, 16], true))) {
            return '';
        }
        $ip = $bin === '' ? false : @inet_ntop($bin);
        return $ip === false ? '' : $ip;
    }
}

if (!function_exists('abort')) {
    /** HTTP hatası fırlatır (ErrorHandler yakalar). */
    function abort(int $code, string $message = ''): never
    {
        throw new HttpException($code, $message);
    }
}

if (!function_exists('is_path')) {
    /** Geçerli yol kalıpla eşleşir mi? * joker: is_path('/hesabim*') */
    function is_path(string $pattern): bool
    {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#u';
        return preg_match($regex, Request::capture()->path()) === 1;
    }
}