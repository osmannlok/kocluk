<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

const SA_SEVERITY_ORDER = [
    'Kritik' => 0,
    'Yüksek' => 1,
    'Orta' => 2,
    'Düşük' => 3,
];

const SA_SAFE_PARTIALS = [
    'resources/views/partials/admin-pagination.php',
    'resources/views/partials/breadcrumb.php',
    'resources/views/partials/cookie-banner.php',
    'resources/views/partials/flash.php',
    'resources/views/partials/pagination.php',
];

function sa_files(string $base, string $dir): array
{
    $root = rtrim($base, '/') . '/' . trim($dir, '/');
    if (!is_dir($root)) {
        return [];
    }

    $out = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        $out[] = ltrim(substr($path, strlen(rtrim($base, '/'))), '/');
    }

    sort($out, SORT_STRING);

    return $out;
}

function sa_add(array &$findings, string $file, int $line, string $severity, string $rule, string $message, string $code): void
{
    $findings[] = [
        'file' => $file,
        'line' => $line,
        'severity' => $severity,
        'rule' => $rule,
        'message' => $message,
        'code' => trim($code),
    ];
}

function sa_short(string $value, int $max = 140): string
{
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? $value;

    return function_exists('mb_substr')
        ? mb_substr($value, 0, $max, 'UTF-8')
        : substr($value, 0, $max);
}

function sa_should_skip(string $line): bool
{
    return stripos($line, '// audit-ok:') !== false;
}

function sa_sql_string(string $value): bool
{
    if (preg_match('/\bSELECT\b[\s\S]*\bFROM\b/i', $value) === 1) {
        return true;
    }

    if (preg_match('/\b(?:INSERT\s+INTO|DELETE\s+FROM|REPLACE\s+INTO)\b/i', $value) === 1) {
        return true;
    }

    if (preg_match('/\bUPDATE\b[\s\S]*\bSET\b/i', $value) === 1) {
        return true;
    }

    if (preg_match('/\b(?:JOIN|UNION)\b/i', $value) === 1) {
        return true;
    }

    if (preg_match('/\b(?:COUNT|SUM|AVG|MIN|MAX|COALESCE|DATE_SUB|DATE_ADD)\s*\(/i', $value) === 1) {
        return true;
    }

    if (preg_match('/\b(?:ORDER\s+BY|GROUP\s+BY|LIMIT|OFFSET)\b/i', $value) === 1
        && preg_match('/[`:]|\b(?:ASC|DESC|FROM|WHERE)\b/i', $value) === 1
    ) {
        return true;
    }

    return preg_match('/\bWHERE\b/i', $value) === 1
        && preg_match('/[`:]|[=<>]/', $value) === 1;
}

function sa_sql_risk(string $line): bool
{
    if (preg_match('/->(?:query|exec)\s*\(/', $line) !== 1
        && preg_match('/(?:["\'])\s*\.\s*\$/', $line) !== 1
        && preg_match('/\$[A-Za-z_][A-Za-z0-9_]*(?:\[[^\]]*\])?\s*\.\s*["\']/', $line) !== 1
    ) {
        return false;
    }

    if (preg_match_all('/[\'"]([^\'"]*)[\'"]/', $line, $matches) <= 0) {
        return false;
    }

    foreach ($matches[1] as $value) {
        if (sa_sql_string($value)) {
            return true;
        }
    }

    return false;
}

function sa_strip_strings(string $expr): string
{
    $stripped = preg_replace("/'(?:\\\\.|[^'\\\\])*'|\"(?:\\\\.|[^\"\\\\])*\"/s", "''", $expr);

    return $stripped ?? $expr;
}

function sa_has_safe_wrapper(string $expr): bool
{
    return preg_match('/(?<![A-Za-z0-9_\\\\])(?:e|htmlspecialchars|htmlentities|csrf_field|csrf_token|money|number_format)\s*\(|\bView::partial\s*\(/', $expr) === 1;
}

function sa_outer_parens(string $expr): bool
{
    $depth = 0;
    $length = strlen($expr);
    $quote = null;
    $escaped = false;

    for ($i = 0; $i < $length; $i++) {
        $ch = $expr[$i];

        if ($quote !== null) {
            if ($escaped) {
                $escaped = false;
            } elseif ($ch === '\\') {
                $escaped = true;
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($ch === '"' || $ch === "'") {
            $quote = $ch;
            continue;
        }

        if ($ch === '(') {
            $depth++;
            continue;
        }

        if ($ch === ')') {
            $depth--;
            if ($depth === 0 && $i !== $length - 1) {
                return false;
            }
        }
    }

    return $depth === 0;
}

function sa_ternary_positions(string $expr): array
{
    $depth = 0;
    $nested = 0;
    $question = null;
    $colon = null;
    $quote = null;
    $escaped = false;
    $length = strlen($expr);

    for ($i = 0; $i < $length; $i++) {
        $ch = $expr[$i];

        if ($quote !== null) {
            if ($escaped) {
                $escaped = false;
            } elseif ($ch === '\\') {
                $escaped = true;
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($ch === '"' || $ch === "'") {
            $quote = $ch;
            continue;
        }

        if ($ch === '(' || $ch === '[' || $ch === '{') {
            $depth++;
            continue;
        }

        if ($ch === ')' || $ch === ']' || $ch === '}') {
            $depth--;
            continue;
        }

        if ($ch === '?' && $depth === 0) {
            if ($question === null) {
                $question = $i;
            } else {
                $nested++;
            }
            continue;
        }

        if ($ch === ':' && $depth === 0 && $question !== null) {
            if ($nested > 0) {
                $nested--;
            } else {
                $colon = $i;
                break;
            }
        }
    }

    return [$question, $colon];
}

function sa_static_value(string $value): bool
{
    $value = trim($value);

    if ($value === '' || preg_match('/^(?:null|true|false)$/i', $value) === 1) {
        return true;
    }

    if (preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1) {
        return true;
    }

    if (preg_match('/^\((?:int|float|bool)\)/', $value) === 1) {
        return true;
    }

    if (sa_has_safe_wrapper($value) || sa_static_ternary($value)) {
        return true;
    }

    $stripped = sa_strip_strings($value);
    $stripped = preg_replace('/\(\s*(?:int|float|bool)\s*\)\s*\$[A-Za-z_][A-Za-z0-9_]*(?:\[[^\]]*\])?/', '0', $stripped) ?? $stripped;
    $stripped = preg_replace('/\b\d+(?:\.\d+)?\b/', '', $stripped) ?? $stripped;
    $stripped = preg_replace('/\b(?:null|true|false)\b/i', '', $stripped) ?? $stripped;
    $stripped = str_replace(["'", '"', '.', ' ', "\t", "\n", "\r", ',', '(', ')'], '', $stripped);

    return $stripped === '';
}

function sa_static_ternary(string $expr): bool
{
    $expr = trim($expr);

    while ($expr !== '' && str_starts_with($expr, '(') && str_ends_with($expr, ')') && sa_outer_parens($expr)) {
        $expr = trim(substr($expr, 1, -1));
    }

    [$question, $colon] = sa_ternary_positions($expr);
    if ($question === null || $colon === null) {
        return false;
    }

    $then = trim(substr($expr, $question + 1, $colon - $question - 1));
    $else = trim(substr($expr, $colon + 1));

    return sa_static_value($then) && sa_static_value($else);
}

function sa_output_safe(string $expr): bool
{
    $expr = trim($expr);

    if ($expr === '' || !str_contains($expr, '$')) {
        return true;
    }

    if (sa_has_safe_wrapper($expr)) {
        return true;
    }

    if (preg_match('/(?:^|[\s(])\$(?:h|targetAttrs|icon|href)\s*\(/', $expr) === 1) {
        return true;
    }

    if (preg_match('/^\$(?:extra|btnCls|expanded|showCls|content)(?:\s*\?\?\s*[\'"][^\'"]*[\'"])?$/', $expr) === 1) {
        return true;
    }

    if (preg_match('/^\((?:int|float|bool)\)/', $expr) === 1) {
        return true;
    }

    if (preg_match('/^(?:count|strlen|mb_strlen|intdiv)\s*\(/', $expr) === 1) {
        return true;
    }

    return sa_static_ternary($expr);
}

function sa_echo_expressions(string $line): array
{
    $out = [];

    if (preg_match_all('/<\?=\s*(.*?)\s*\?>/s', $line, $matches) > 0) {
        foreach ($matches[1] as $expr) {
            $out[] = $expr;
        }
    }

    if (preg_match_all('/\becho\s+(.+?);/s', $line, $matches) > 0) {
        foreach ($matches[1] as $expr) {
            $out[] = $expr;
        }
    }

    return $out;
}

function sa_is_safe_partial(string $rel): bool
{
    return in_array($rel, SA_SAFE_PARTIALS, true);
}

function sa_is_image_upload(string $rel): bool
{
    return $rel === 'app/Services/ImageUploadService.php';
}

function sa_is_email_template(string $rel): bool
{
    return str_starts_with($rel, 'resources/views/emails/')
        || $rel === 'resources/views/layouts/email.php';
}

function sa_common_checks(string $rel, int $lineNo, string $line, array &$findings): void
{
    if (sa_sql_risk($line)) {
        sa_add($findings, $rel, $lineNo, 'Kritik', 'SQL', 'SQL içinde değişken birleştirme', $line);
    }

    if ($rel !== 'app/Core/Request.php' && preg_match('/\$_(GET|POST|REQUEST)\b/', $line) === 1) {
        sa_add($findings, $rel, $lineNo, 'Yüksek', 'INPUT', 'Request dışında süperglobal kullanımı', $line);
    }

    if (preg_match('/\beval\s*\(/', $line) === 1) {
        sa_add($findings, $rel, $lineNo, 'Kritik', 'RCE', 'eval() kullanımı', $line);
    }

    if (preg_match('/\b(exec|shell_exec|system|passthru)\s*\(/', $line) === 1) {
        sa_add($findings, $rel, $lineNo, 'Kritik', 'RCE', 'Kabuk komutu çalıştırma', $line);
    }

    if (preg_match('/\bunserialize\s*\(/', $line) === 1) {
        sa_add($findings, $rel, $lineNo, 'Yüksek', 'DESERIALIZE', 'unserialize() kullanımı', $line);
    }

    if (preg_match('/\bextract\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE|FILES|SERVER)\b/', $line) === 1) {
        sa_add($findings, $rel, $lineNo, 'Yüksek', 'EXTRACT', 'Süperglobal extract()', $line);
    }

    if (preg_match('/\b(md5|sha1)\s*\(/i', $line) === 1 && preg_match('/password|passwd|parola|sifre|şifre|pwd/i', $line) === 1) {
        sa_add($findings, $rel, $lineNo, 'Kritik', 'PASSWORD', 'Parola için md5/sha1 kullanımı', $line);
    }

    if (!sa_is_image_upload($rel) && preg_match('/\bmove_uploaded_file\s*\(/', $line) === 1) {
        sa_add($findings, $rel, $lineNo, 'Yüksek', 'UPLOAD', 'ImageUploadService dışında move_uploaded_file', $line);
    }

    if (!sa_is_image_upload($rel) && preg_match('/\bfile_put_contents\s*\(/', $line) === 1) {
        sa_add($findings, $rel, $lineNo, 'Orta', 'UPLOAD', 'ImageUploadService dışında file_put_contents', $line);
    }

    if (
        preg_match('/\b(?:float|double)\s*\(|\b(?:round|ceil|floor)\s*\(/', $line) === 1
        && preg_match('/price|amount|subtotal|grand_total|line_total|total_revenue|total_amount|discount|shipping|cod_fee|tax|kdv|tutar|fiyat|indirim|kargo|kupon|coupon|balance|bakiye|revenue|ciro|payment/i', $line) === 1
    ) {
        sa_add($findings, $rel, $lineNo, 'Orta', 'MONEY', 'Para bağlamında float/round/ondalık işlem', $line);
    }
}

function sa_view_checks(string $rel, int $lineNo, string $line, array &$findings): void
{
    if (!sa_is_safe_partial($rel)) {
        foreach (sa_echo_expressions($line) as $expr) {
            if (!sa_output_safe($expr)) {
                sa_add($findings, $rel, $lineNo, 'Yüksek', 'XSS', 'Çıktı e()/htmlspecialchars() ile kaçırılmamış: ' . sa_short($expr), $line);
            }
        }
    }

    $email = sa_is_email_template($rel);
    $phpComment = preg_match('/<\?php\s*\/[\/\*]/', $line) === 1;

    if (!$phpComment && preg_match('/<script\b(?![^>]*\bsrc\s*=)/i', $line) === 1) {
        sa_add($findings, $rel, $lineNo, $email ? 'Düşük' : 'Yüksek', 'CSP', 'Satır içi <script> bloğu', $line);
    }

    if (!$phpComment && preg_match('/\sstyle\s*=\s*["\']/i', $line) === 1) {
        sa_add($findings, $rel, $lineNo, $email ? 'Düşük' : 'Orta', 'CSP', 'Satır içi style özniteliği', $line);
    }

    if (!$phpComment && preg_match('/\son[a-z]+\s*=\s*["\']/i', $line) === 1) {
        sa_add($findings, $rel, $lineNo, $email ? 'Düşük' : 'Yüksek', 'CSP', 'Satır içi on* olay özniteliği', $line);
    }
}

function sa_scan_file(string $base, string $rel, bool $isView, array &$findings): void
{
    $lines = file($base . '/' . $rel, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $index => $line) {
        if (sa_should_skip($line)) {
            continue;
        }

        $lineNo = $index + 1;
        sa_common_checks($rel, $lineNo, $line, $findings);

        if ($isView) {
            sa_view_checks($rel, $lineNo, $line, $findings);
        }
    }
}

function sa_sort(array &$findings): void
{
    usort($findings, static function (array $a, array $b): int {
        $sa = SA_SEVERITY_ORDER[$a['severity']] ?? 9;
        $sb = SA_SEVERITY_ORDER[$b['severity']] ?? 9;

        return [$sa, $a['file'], $a['line']] <=> [$sb, $b['file'], $b['line']];
    });
}

function sa_render(array $findings): string
{
    if ($findings === []) {
        return "Bulgu yok.\n";
    }

    $out = '';
    $counts = [];

    foreach ($findings as $finding) {
        $out .= sprintf(
            "%s:%d | %s | %s | %s\n",
            $finding['file'],
            $finding['line'],
            $finding['severity'],
            $finding['rule'],
            $finding['message']
        );
        $counts[$finding['severity']] = ($counts[$finding['severity']] ?? 0) + 1;
    }

    $out .= "\nÖzet:";
    foreach (['Kritik', 'Yüksek', 'Orta', 'Düşük'] as $severity) {
        if (isset($counts[$severity])) {
            $out .= ' ' . $severity . '=' . $counts[$severity];
        }
    }
    $out .= ' toplam=' . count($findings) . "\n";

    return $out;
}

function sa_main(): int
{
    global $argv;

    $base = rtrim((string) ($argv[1] ?? dirname(__DIR__)), '/');
    if (!is_dir($base)) {
        fwrite(STDERR, 'Dizin bulunamadı: ' . $base . "\n");

        return 2;
    }

    $findings = [];

    foreach (sa_files($base, 'app') as $rel) {
        sa_scan_file($base, $rel, false, $findings);
    }

    foreach (sa_files($base, 'resources/views') as $rel) {
        sa_scan_file($base, $rel, true, $findings);
    }

    sa_sort($findings);
    echo sa_render($findings);

    foreach ($findings as $finding) {
        if ($finding['severity'] === 'Kritik' || $finding['severity'] === 'Yüksek') {
            return 1;
        }
    }

    return 0;
}

exit(sa_main());
