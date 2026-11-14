<?php
// app/Core/Router.php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;
use Closure;
use LogicException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

final class Router
{
    private const DEFAULT_CONSTRAINTS = [
        'id' => '\d+',
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ];

    /** @var list<array{method:string,regex:string,handler:array|callable,middleware:list<string>}> */
    private array $routes = [];
    /** @var list<array{prefix:string,middleware:list<string>}> */
    private array $groups = [];
    /** @var array<string,MiddlewareInterface> */
    private array $instances = [];

    public function get(string $path, array|callable $handler, array $opts = []): void
    {
        $this->add('GET', $path, $handler, $opts);
    }

    public function post(string $path, array|callable $handler, array $opts = []): void
    {
        $this->add('POST', $path, $handler, $opts);
    }

    public function put(string $path, array|callable $handler, array $opts = []): void
    {
        $this->add('PUT', $path, $handler, $opts);
    }

    public function delete(string $path, array|callable $handler, array $opts = []): void
    {
        $this->add('DELETE', $path, $handler, $opts);
    }

    public function group(array $attrs, callable $fn): void
    {
        $this->groups[] = [
            'prefix' => (string) ($attrs['prefix'] ?? ''),
            'middleware' => self::toList($attrs['middleware'] ?? []),
        ];
        try {
            $fn($this);
        } finally {
            array_pop($this->groups);
        }
    }

    public function dispatch(Request $r): Response
    {
        $method = strtoupper($r->method());
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = self::normalize($r->path());
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $m) !== 1) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[$route['method']] = true;
                continue;
            }
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = rawurldecode($v);
                }
            }
            $r->setRouteParams($params);
            return $this->pipeline($r, $route['middleware'], fn (Request $req): Response => $this->invoke($route['handler'], $req));
        }

        // Eşleşme yoksa da global middleware (güvenlik başlıkları, csrf) çalışsın diye hata çekirdekte fırlatılır.
        $core = static function (Request $req) use ($allowed): Response {
            if ($allowed !== []) {
                throw new HttpException(405, 'Bu istek yöntemi bu adres için desteklenmiyor.');
            }
            throw new HttpException(404, 'Aradığınız sayfa bulunamadı.');
        };
        return $this->pipeline($r, [], $core);
    }

    private function add(string $method, string $path, array|callable $handler, array $opts): void
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groups as $g) {
            $prefix .= $g['prefix'];
            $middleware = array_merge($middleware, $g['middleware']);
        }
        $middleware = array_merge($middleware, self::toList($opts['middleware'] ?? []));
        $full = self::normalize($prefix . '/' . ltrim($path, '/'));

        $this->routes[] = [
            'method' => $method,
            'regex' => self::compile($full),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    private static function toList(array|string $v): array
    {
        return is_string($v) ? ($v === '' ? [] : [$v]) : array_values($v);
    }

    private static function normalize(string $p): string
    {
        $p = (string) preg_replace('#/+#', '/', '/' . $p);
        return $p === '/' ? '/' : rtrim($p, '/');
    }

    private static function compile(string $path): string
    {
        preg_match_all(
            '/\{(\w+)(?::((?:[^{}]|\{[^{}]*\})+))?\}/',
            $path,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        $regex = '';
        $pos = 0;
        foreach ($matches as $set) {
            $full = $set[0][0];
            $offset = $set[0][1];
            $regex .= preg_quote(substr($path, $pos, $offset - $pos), '#');
            $name = $set[1][0];
            $constraint = (isset($set[2]) && $set[2][0] !== '')
                ? $set[2][0]
                : (self::DEFAULT_CONSTRAINTS[$name] ?? '[^/]+');
            $regex .= '(?P<' . $name . '>' . str_replace('#', '\#', $constraint) . ')';
            $pos = $offset + strlen($full);
        }
        $regex .= preg_quote(substr($path, $pos), '#');
        return '#^' . $regex . '$#u';
    }

    /** @param list<string> $routeMiddleware */
    private function pipeline(Request $r, array $routeMiddleware, callable $core): Response
    {
        $stack = array_merge((array) Config::get('middleware.global', []), $routeMiddleware);
        $next = $core;
        foreach (array_reverse($stack) as $spec) {
            [$mw, $args] = $this->resolveMiddleware((string) $spec);
            $current = $next;
            $next = static fn (Request $req): Response => $mw->handle($req, $current, $args);
        }
        return $next($r);
    }

    /** @return array{0:MiddlewareInterface,1:list<string>} */
    private function resolveMiddleware(string $spec): array
    {
        $pos = strpos($spec, ':');
        $name = $pos === false ? $spec : substr($spec, 0, $pos);
        $argStr = $pos === false ? '' : substr($spec, $pos + 1);
        $args = $argStr === '' ? [] : array_map('trim', explode(',', $argStr));

        $aliases = (array) Config::get('middleware.aliases', []);
        $class = (string) ($aliases[$name] ?? $name);
        // Config'te 'Auth' yazsa da dosya 'AuthMiddleware' olabilir: ikisi de denenir.
        if (!class_exists($class) && class_exists($class . 'Middleware')) {
            $class .= 'Middleware';
        }
        if (!class_exists($class) || !is_subclass_of($class, MiddlewareInterface::class)) {
            throw new RuntimeException("Middleware çözümlenemedi: {$name}");
        }
        return [$this->instances[$class] ??= new $class(), $args];
    }

    private function invoke(array|callable $handler, Request $r): Response
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            [$class, $method] = $handler;
            if (!class_exists($class) || !method_exists($class, $method) || str_starts_with($method, '__')) {
                throw new LogicException("Geçersiz route işleyicisi: {$class}@{$method}");
            }
            $ref = new ReflectionMethod($class, $method);
            if (!$ref->isPublic()) {
                throw new LogicException("Route işleyicisi public değil: {$class}@{$method}");
            }
            $controller = new $class();
            $callable = [$controller, $method];
        } else {
            $callable = Closure::fromCallable($handler);
            $ref = new ReflectionFunction($callable);
        }

        $args = [];
        foreach ($ref->getParameters() as $p) {
            $type = $p->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_a($type->getName(), Request::class, true)) {
                $args[] = $r;
                continue;
            }
            $raw = $r->routeParam($p->getName());
            if ($raw !== null) {
                $args[] = $this->cast($raw, $type);
                continue;
            }
            if ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
                continue;
            }
            if ($type !== null && $type->allowsNull()) {
                $args[] = null;
                continue;
            }
            throw new LogicException('Route parametresi çözümlenemedi: ' . $p->getName());
        }

        return $this->toResponse($callable(...$args));
    }

    private function cast(mixed $raw, mixed $type): mixed
    {
        if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
            if ($type->getName() === 'int') {
                $i = filter_var($raw, FILTER_VALIDATE_INT);
                if ($i === false) {
                    throw new HttpException(404, 'Aradığınız sayfa bulunamadı.');
                }
                return $i;
            }
            if ($type->getName() === 'string') {
                return (string) $raw;
            }
        }
        return $raw;
    }

    private function toResponse(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            is_string($result) => Response::html($result),
            is_array($result) => Response::json($result),
            $result === null => Response::text('', 204),
            default => throw new LogicException('Route işleyicisi geçersiz yanıt döndürdü.'),
        };
    }
}