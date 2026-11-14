<?php
// app/Controllers/BaseController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use stdClass;

abstract class BaseController
{
    protected function view(string $name, array $d = [], string $layout = 'layouts.main'): Response
    {
        return Response::html(View::render($name, $d, $layout));
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function ok(array $data = []): Response
    {
        return Response::json(['ok' => true, 'data' => $data === [] ? new stdClass() : $data]);
    }

    protected function fail(string $code, string $msg, int $s = 400, array $errors = []): Response
    {
        return Response::json([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $msg,
                'errors' => $errors === [] ? new stdClass() : $errors,
            ],
        ], $s);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    /** @return array<string,mixed> yalnızca kuralı olan, tip dönüştürülmüş alanlar (mass assignment yok) */
    protected function validate(Request $r, array $rules, array $messages = []): array
    {
        $input = $r->all();
        $v = Validator::make($input, $rules, $messages);
        if ($v->fails()) {
            // Parola/token/kart/TC alanları old() verisine asla taşınmaz.
            $old = array_filter(
                $input,
                static fn ($key): bool => preg_match('/pass|parola|sifre|token|card|kart|cvv|tc_identity/i', (string) $key) !== 1,
                ARRAY_FILTER_USE_KEY
            );
            throw new ValidationException($v->errors(), $old);
        }
        return $v->validated();
    }

    protected function flash(string $type, string $msg): void
    {
        \flash($type, $msg);
    }
}