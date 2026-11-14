<?php
// app/Middleware/MiddlewareInterface.php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

interface MiddlewareInterface
{
    /** @param callable(Request): Response $next */
    public function handle(Request $r, callable $next, array $args = []): Response;
}