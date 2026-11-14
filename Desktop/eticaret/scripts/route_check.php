<?php
// scripts/route_check.php

declare(strict_types=1);

use App\Core\Config;

// SADECE CLI: web üzerinden çağrılırsa hiçbir şey çalıştırılmaz.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

/**
 * Route dosyalarının $router değişkeni olarak verilen kayıt nesnesi.
 * Gerçek Router'ın get/post/put/delete/group imzasını taklit eder; hiçbir istek işlemez.
 */
final class RouteRecorder
{
    /** @var list<array<string,mixed>> */
    private array $routes = [];

    /** @var list<array{prefix:string,middleware:list<string>}> */
    private array $stack = [];

    private string $file = '';

    public function setFile(string $file): void
    {
        $this->file = basename($file);
    }

    /** @param array<string,mixed> $opts */
    public function get(string $path, array|callable $handler, array $opts = []): void
    {
        $this->add('GET', $path, $handler, $opts);
    }

    /** @param array<string,mixed> $opts */
    public function post(string $path, array|callable $handler, array $opts = []): void
    {
        $this->add('POST', $path, $handler, $opts);
    }

    /** @param array<string,mixed> $opts */
    public function put(string $path, array|callable $handler, array $opts = []): void
    {
        $this->add('PUT', $path, $handler, $opts);
    }

    /** @param array<string,mixed> $opts */
    public function delete(string $path, array|callable $handler, array $opts = []): void
    {
        $this->add('DELETE', $path, $handler, $opts);
    }

    /** @param array<string,mixed> $attrs */
    public function group(array $attrs, callable $fn): void
    {
        $this->stack[] = [
            'prefix'     => (string) ($attrs['prefix'] ?? ''),
            'middleware' => self::toList($attrs['middleware'] ?? []),
        ];
        try {
            $fn($this);
        } finally {
            array_pop($this->stack);
        }
    }

    /** @return list<array<string,mixed>> */
    public function routes(): array
    {
        return $this->routes;
    }

    /** @param array<string,mixed> $opts */
    private function add(string $method, string $path, array|callable $handler, array $opts): void
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->stack as $group) {
            $p = trim($group['prefix'], '/');
            if ($p !== '') {
                $prefix .= '/' . $p;
            }
            $middleware = array_merge($middleware, $group['middleware']);
        }
        $middleware = array_merge($middleware, self::toList($opts['middleware'] ?? []));

        $this->routes[] = [
            'method'     => $method,
            'path'       => '/' . trim($prefix . '/' . ltrim($path, '/'), '/'),
            'handler'    => $handler,
            'middleware' => $middleware,
            'file'       => $this->file,
            'order'      => count($this->routes),
        ];
    }

    /** @return list<string> */
    private static function toList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}

/** Denetim kuralları; her kural satır üretir, hiçbir şey değiştirmez. */
final class RouteChecker
{
    /** Seed'deki 23 permission kodu (01_auth_rbac.sql); yazım hatası yakalamak için. */
    private const KNOWN_PERMS = [
        'dashboard.view', 'product.view', 'product.create', 'product.update', 'product.delete',
        'category.manage', 'stock.manage', 'order.view', 'order.update_status', 'order.cancel',
        'order.refund', 'payment.confirm', 'shipment.manage', 'customer.view', 'coupon.manage',
        'banner.manage', 'menu.manage', 'page.manage', 'setting.manage', 'user.manage',
        'role.manage', 'report.view', 'audit.view',
    ];

    /** 03-klasor-yapisi.md URL haritası (tek kaynak); yönetim yolları /yonetim önekiyle genişletilir. */
    private const PUBLIC_MAP = [
        '/', '/urunler', '/urun/{slug}', '/kategori/{slug}', '/yore/{slug}', '/ara', '/ara/oneri',
        '/sayfa/{slug}', '/iletisim', '/sitemap.xml',
        '/giris', '/kayit', '/cikis', '/eposta-dogrula', '/eposta-dogrula/{token}', '/eposta-dogrula/tekrar',
        '/sifremi-unuttum', '/sifre-sifirla', '/sifre-sifirla/{token}',
        '/sepet', '/sepet/ozet', '/sepet/ekle', '/sepet/guncelle', '/sepet/sil', '/sepet/kupon', '/sepet/kupon/kaldir',
        '/odeme', '/odeme/ozet', '/odeme/siparis-ver', '/siparis/tamamlandi/{orderNumber}',
        '/odeme/kart/{orderNumber}', '/odeme/paytr/bildirim', '/odeme/basarili/{orderNumber}', '/odeme/basarisiz/{orderNumber}',
        '/hesabim', '/hesabim/siparisler', '/hesabim/siparisler/{orderNumber}', '/hesabim/siparisler/{orderNumber}/iptal',
        '/hesabim/adresler', '/hesabim/adresler/liste', '/hesabim/adresler/{id}/guncelle',
        '/hesabim/adresler/{id}/sil', '/hesabim/adresler/{id}/varsayilan', '/hesabim/profil', '/hesabim/sifre',
    ];

    private const ADMIN_MAP = [
        '', '/urunler', '/kategoriler', '/yoreler', '/stok', '/siparisler', '/musteriler', '/kuponlar',
        '/bannerlar', '/duyurular', '/menuler', '/sayfalar', '/ayarlar', '/mesajlar', '/kullanicilar',
        '/roller', '/denetim', '/guvenlik-olaylari',
    ];

    /** @var list<array{check:string,level:string,method:string,path:string,detail:string}> */
    private array $rows = [];

    /**
     * @param list<array<string,mixed>> $routes
     * @param list<string> $globalMiddleware
     * @param list<string> $aliases
     * @param list<string> $csrfExcept
     */
    public function __construct(
        private readonly array $routes,
        private readonly array $globalMiddleware,
        private readonly array $aliases,
        private readonly array $csrfExcept
    ) {
    }

    /** @return list<array{check:string,level:string,method:string,path:string,detail:string}> */
    public function run(): array
    {
        $this->checkAdminPermissions();
        $this->checkDuplicatesAndShadows();
        $this->checkHandlers();
        $this->checkUrlMap();
        $this->checkCsrf();

        return $this->rows;
    }

    private function row(string $check, string $level, string $method, string $path, string $detail): void
    {
        $this->rows[] = ['check' => $check, 'level' => $level, 'method' => $method, 'path' => $path, 'detail' => $detail];
    }

    /** (1) /yonetim altında auth ve perm middleware'i. */
    private function checkAdminPermissions(): void
    {
        foreach ($this->routes as $r) {
            $path = (string) $r['path'];
            if ($path !== '/yonetim' && !str_starts_with($path, '/yonetim/')) {
                continue;
            }
            $mw = $r['middleware'];
            $perms = array_values(array_filter($mw, static fn (string $m): bool => str_starts_with($m, 'perm:')));

            if (!in_array('auth', $mw, true)) {
                $this->row('1-PERM', 'HATA', $r['method'], $path, 'auth middleware yok');
            }
            if ($perms === []) {
                $this->row('1-PERM', 'HATA', $r['method'], $path, 'perm middleware yok');
            } elseif ($path !== '/yonetim' && array_diff($perms, ['perm:dashboard.view']) === []) {
                $this->row('1-PERM', 'HATA', $r['method'], $path, 'yalnızca taban yetki (dashboard.view); kendi permission kodu yok');
            }
            foreach ($perms as $perm) {
                foreach (explode(',', substr($perm, 5)) as $code) {
                    if ($code === '' || !in_array(trim($code), self::KNOWN_PERMS, true)) {
                        $this->row('1-PERM', 'HATA', $r['method'], $path, 'bilinmeyen permission kodu: ' . $code);
                    }
                }
            }
        }
    }

    /** (2) Aynı yol+yöntem tekrarı ve sıralama nedeniyle gölgelenen rotalar. */
    private function checkDuplicatesAndShadows(): void
    {
        $seen = [];
        foreach ($this->routes as $r) {
            $key = $r['method'] . ' ' . $this->strictKey((string) $r['path']);
            if (isset($seen[$key])) {
                $first = $seen[$key];
                $this->row('2-TEKRAR', 'HATA', $r['method'], $r['path'], 'tekrar; ilk tanım: ' . $first['file'] . ' #' . $first['order']);
                continue;
            }
            $seen[$key] = $r;
        }

        $count = count($this->routes);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $this->routes[$i];
                $b = $this->routes[$j];
                if ($a['method'] !== $b['method'] || !$this->shadows((string) $a['path'], (string) $b['path'])) {
                    continue;
                }
                $this->row('2-TEKRAR', 'HATA', $b['method'], $b['path'], 'gölgelenir (önce tanımlı: ' . $a['path'] . ')');
            }
        }
    }

    /** (3) Controller sınıfı/metodu, middleware alias ve argüman biçimi. */
    private function checkHandlers(): void
    {
        foreach ($this->routes as $r) {
            $h = $r['handler'];
            if (is_array($h)) {
                $class = $h[0] ?? null;
                $method = $h[1] ?? null;
                if (!is_string($class) || !is_string($method)) {
                    $this->row('3-ISLEYICI', 'HATA', $r['method'], $r['path'], 'geçersiz işleyici dizisi');
                } elseif (!class_exists($class)) {
                    $this->row('3-ISLEYICI', 'HATA', $r['method'], $r['path'], 'sınıf yok: ' . $class);
                } elseif (!method_exists($class, $method)) {
                    $this->row('3-ISLEYICI', 'HATA', $r['method'], $r['path'], 'metot yok: ' . $class . '::' . $method);
                } elseif (!(new ReflectionMethod($class, $method))->isPublic()) {
                    $this->row('3-ISLEYICI', 'HATA', $r['method'], $r['path'], 'metot public değil: ' . $class . '::' . $method);
                }
            }

            foreach ($r['middleware'] as $mw) {
                $name = explode(':', $mw, 2)[0];
                // Router, alias yoksa Sınıf.'Middleware' dener; aynı mantık burada.
                if (!in_array($name, $this->aliases, true) && !class_exists($name) && !class_exists($name . 'Middleware')) {
                    $this->row('3-ISLEYICI', 'HATA', $r['method'], $r['path'], 'tanımsız middleware: ' . $mw);
                }
                if ($name === 'throttle' && preg_match('/^throttle:[1-9]\d*,[1-9]\d*$/', $mw) !== 1) {
                    $this->row('3-ISLEYICI', 'HATA', $r['method'], $r['path'], 'throttle biçimi geçersiz: ' . $mw);
                }
                if ($name === 'perm' && !str_contains($mw, ':')) {
                    $this->row('3-ISLEYICI', 'HATA', $r['method'], $r['path'], 'perm kodu verilmemiş');
                }
            }
        }
    }

    /** (4) 03 URL haritasında olup route'u olmayan yollar. */
    private function checkUrlMap(): void
    {
        $have = [];
        foreach ($this->routes as $r) {
            $have[$this->looseKey((string) $r['path'])] = true;
        }

        $map = self::PUBLIC_MAP;
        foreach (self::ADMIN_MAP as $suffix) {
            $map[] = '/yonetim' . $suffix;
        }
        foreach ($map as $path) {
            if (!isset($have[$this->looseKey($path)])) {
                $this->row('4-URL-HARITASI', 'HATA', '-', $path, 'route tanımı yok');
            }
        }
    }

    /** (5) csrf_except dışında CSRF'siz yazma ucu. */
    private function checkCsrf(): void
    {
        $writeRoutes = [];
        foreach ($this->routes as $r) {
            if (!in_array($r['method'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                continue;
            }
            $writeRoutes[(string) $r['path']] = true;
            $protected = in_array('csrf', $this->globalMiddleware, true) || in_array('csrf', $r['middleware'], true);
            $excepted = in_array($r['path'], $this->csrfExcept, true);

            if ($excepted) {
                $this->row('5-CSRF', 'BILGI', $r['method'], $r['path'], 'csrf_except istisnası (hash doğrulaması gerekir)');
            } elseif (!$protected) {
                $this->row('5-CSRF', 'HATA', $r['method'], $r['path'], 'CSRF middleware yok ve istisna listesinde değil');
            }
        }
        foreach ($this->csrfExcept as $path) {
            if (!isset($writeRoutes[$path])) {
                $this->row('5-CSRF', 'UYARI', '-', $path, 'csrf_except girdisinin yazma rotası yok');
            }
        }
        if (!in_array('csrf', $this->globalMiddleware, true)) {
            $this->row('5-CSRF', 'UYARI', '-', '-', 'csrf global middleware listesinde değil');
        }
    }

    // ------------------------------------------------------------------ yardımcılar

    /** @return list<string> */
    private static function segments(string $path): array
    {
        $trim = trim($path, '/');

        return $trim === '' ? [] : explode('/', $trim);
    }

    /** Parametre türü: d=rakam, s=slug, x=herhangi, r:<regex>=özel; parametre değilse null. */
    private static function kind(string $segment): ?string
    {
        if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::(.+))?\}$/', $segment, $m) !== 1) {
            return null;
        }
        if (isset($m[2]) && $m[2] !== '') {
            return $m[2] === '\d+' ? 'd' : 'r:' . $m[2];
        }

        return match ($m[1]) {
            'id'    => 'd',
            'slug'  => 's',
            default => 'x',
        };
    }

    private static function kindRegex(string $kind): string
    {
        return match (true) {
            $kind === 'd'                => '\d+',
            $kind === 's'                => '[a-z0-9]+(?:-[a-z0-9]+)*',
            str_starts_with($kind, 'r:') => substr($kind, 2),
            default                      => '[^/]+',
        };
    }

    /** Parametre adını yok sayar ama türünü korur. */
    private function strictKey(string $path): string
    {
        $out = [];
        foreach (self::segments($path) as $seg) {
            $k = self::kind($seg);
            $out[] = $k === null ? $seg : '{' . $k . '}';
        }

        return '/' . implode('/', $out);
    }

    /** Parametre adını ve türünü yok sayar (URL haritası karşılaştırması). */
    private function looseKey(string $path): string
    {
        $out = [];
        foreach (self::segments($path) as $seg) {
            $out[] = self::kind($seg) === null ? $seg : '{}';
        }

        return '/' . implode('/', $out);
    }

    /** A önce tanımlıysa ve B'nin isteklerini yakalıyorsa true. */
    private function shadows(string $a, string $b): bool
    {
        $sa = self::segments($a);
        $sb = self::segments($b);
        if (count($sa) !== count($sb)) {
            return false;
        }

        $evidence = false;
        foreach ($sa as $i => $segA) {
            $segB = $sb[$i];
            $kindA = self::kind($segA);
            $kindB = self::kind($segB);

            if ($kindA === null) {
                if ($segA !== $segB) {
                    return false;
                }
                continue;
            }
            if ($kindB !== null) {
                // İkisi de parametre: A daha geniş (x) ve B daha dar ise B gölgelenir.
                if ($kindA === 'x' && $kindB !== 'x') {
                    $evidence = true;
                } elseif ($kindA !== $kindB && $kindA !== 'x') {
                    return false;
                }
                continue;
            }
            // A parametre, B sabit: A'nın kuralı B'nin sabit parçasına uyuyorsa gölgeleme.
            if (@preg_match('#^' . self::kindRegex($kindA) . '$#', $segB) !== 1) {
                return false;
            }
            $evidence = true;
        }

        return $evidence;
    }
}

/** Route dosyasını kayıt nesnesiyle yükler ($router değişkeni dosyada böyle görünür). */
function load_route_file(RouteRecorder $router, string $file): void
{
    $router->setFile($file);
    (static function () use ($router, $file): void {
        require $file;
    })();
}

/** Çok baytlı güvenli sağ doldurma (mb_str_pad PHP 8.3'te geldi). */
function pad_right(string $text, int $width): string
{
    return $text . str_repeat(' ', max(0, $width - mb_strlen($text)));
}

$base = dirname(__DIR__);
$autoload = $base . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "vendor/autoload.php yok; önce composer install çalıştırın.\n");
    exit(1);
}
require $autoload;

try {
    Config::load($base);
} catch (Throwable $e) {
    fwrite(STDERR, 'Yapılandırma yüklenemedi: ' . $e->getMessage() . "\n");
    exit(1);
}

$recorder = new RouteRecorder();
$rows = [];
foreach (['routes/web.php', 'routes/admin.php'] as $relative) {
    $file = $base . '/' . $relative;
    if (!is_file($file)) {
        $rows[] = ['check' => '0-DOSYA', 'level' => 'HATA', 'method' => '-', 'path' => $relative, 'detail' => 'dosya yok'];
        continue;
    }
    try {
        load_route_file($recorder, $file);
    } catch (Throwable $e) {
        $rows[] = ['check' => '0-DOSYA', 'level' => 'HATA', 'method' => '-', 'path' => $relative, 'detail' => 'yüklenemedi: ' . $e->getMessage()];
    }
}

$toStrings = static fn (mixed $v): array => is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
$aliasConfig = Config::get('middleware.aliases', []);

$checker = new RouteChecker(
    $recorder->routes(),
    $toStrings(Config::get('middleware.global', [])),
    is_array($aliasConfig) ? array_map('strval', array_keys($aliasConfig)) : [],
    $toStrings(Config::get('security.csrf_except', []))
);
$rows = array_merge($rows, $checker->run());

echo 'ROUTE KONTROL RAPORU | rota sayısı: ' . count($recorder->routes()) . "\n";

$errors = 0;
if ($rows === []) {
    echo "Sorun bulunmadı.\n";
} else {
    $headers = ['KONTROL', 'SEVİYE', 'YÖNTEM', 'YOL', 'DETAY'];
    $widths = array_map('mb_strlen', $headers);
    foreach ($rows as $row) {
        foreach ([$row['check'], $row['level'], $row['method'], $row['path'], $row['detail']] as $i => $cell) {
            $widths[$i] = max($widths[$i], mb_strlen($cell));
        }
    }
    $line = static function (array $cells) use ($widths): string {
        $out = [];
        foreach ($cells as $i => $cell) {
            $out[] = pad_right((string) $cell, $widths[$i]);
        }

        return implode(' | ', $out);
    };
    echo $line($headers) . "\n" . str_repeat('-', array_sum($widths) + 3 * (count($widths) - 1)) . "\n";
    foreach ($rows as $row) {
        echo $line([$row['check'], $row['level'], $row['method'], $row['path'], $row['detail']]) . "\n";
        if ($row['level'] === 'HATA') {
            $errors++;
        }
    }
    echo "\nHATA: {$errors} | toplam satır: " . count($rows) . "\n";
}

exit($errors > 0 ? 1 : 0);