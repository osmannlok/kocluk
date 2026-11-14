<?php

declare(strict_types=1);

namespace App\Services;

class Router
{
    public function __construct(private array $routes)
    {
    }

    public function dispatch(string $method, string $uri): void
    {
        $methodRoutes = $this->routes[$method] ?? [];

        foreach ($methodRoutes as $pattern => $handler) {
            $params = $this->match($pattern, $uri);

            if ($params !== null) {
                [$controllerClass, $action] = $handler;
                $controller = new $controllerClass();
                call_user_func_array([$controller, $action], $params);
                return;
            }
        }

        http_response_code(404);
        require BASE_PATH . '/views/errors/404.php';
    }

    private function match(string $pattern, string $uri): ?array
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        if (count($patternParts) !== count($uriParts)) {
            return null;
        }

        $params = [];

        foreach ($patternParts as $index => $part) {
            if (preg_match('/^\{(\w+)\}$/', $part, $matches)) {
                $params[] = $uriParts[$index];
                continue;
            }

            if ($part !== $uriParts[$index]) {
                return null;
            }
        }

        return $params;
    }
}