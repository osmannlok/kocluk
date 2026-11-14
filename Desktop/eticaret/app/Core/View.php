<?php
// app/Core/View.php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    public static function share(string $k, mixed $v): void
    {
        self::$shared[$k] = $v;
    }

    public static function render(string $name, array $data = [], ?string $layout = null): string
    {
        $vars = array_merge(self::$shared, $data);
        $content = self::renderFile(self::resolve($name), $vars);
        if ($layout === null) {
            return $content;
        }
        return self::renderFile(self::resolve($layout), array_merge($vars, ['content' => $content]));
    }

    public static function partial(string $name, array $data = []): string
    {
        return self::renderFile(self::resolve($name), array_merge(self::$shared, $data));
    }

    // Path traversal koruması: ad katı desenle doğrulanır, realpath view dizini içinde kalmalıdır.
    private static function resolve(string $name): string
    {
        if (preg_match('/^[a-z0-9_-]+(?:\.[a-z0-9_-]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException('Geçersiz view adı.');
        }
        $base = realpath(Config::basePath('resources/views'));
        if ($base === false) {
            throw new RuntimeException('View dizini bulunamadı.');
        }
        $file = realpath($base . '/' . str_replace('.', '/', $name) . '.php');
        if ($file === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("View bulunamadı: {$name}");
        }
        return $file;
    }

    private static function renderFile(string $file, array $vars): string
    {
        $level = ob_get_level();
        try {
            return (static function (string $__file, array $__vars): string {
                extract($__vars, EXTR_SKIP);
                ob_start();
                require $__file;
                return (string) ob_get_clean();
            })($file, $vars);
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }
}