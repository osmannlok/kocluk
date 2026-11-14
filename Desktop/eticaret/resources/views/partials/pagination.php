<?php
declare(strict_types=1);

// resources/views/partials/pagination.php
// Girdi: $pager {page,pages,total}, $baseUrl (yol). Mevcut sorgu dizesi korunur, yalnızca page değişir.
// Mobilde kompakt (Önceki / Sayfa x/y / Sonraki), masaüstünde numaralı; rel=prev/next.

use App\Core\Request;

$page = max(1, (int) ($pager['page'] ?? 1));
$pages = max(1, (int) ($pager['pages'] ?? 1));
$page = min($page, $pages);

if ($pages <= 1) {
    return;
}

$base = (string) ($baseUrl ?? '/');
$basePath = (string) (parse_url($base, PHP_URL_PATH) ?? '/');
$baseQuery = [];
$rawQuery = parse_url($base, PHP_URL_QUERY);
if (is_string($rawQuery) && $rawQuery !== '') {
    parse_str($rawQuery, $baseQuery);
}
$baseHost = preg_match('#^https?://[^/]+#i', $base, $hm) === 1 ? $hm[0] : '';

// Mevcut istek sorgusu Request üzerinden korunur; page her zaman yeniden hesaplanır.
$requestQuery = Request::capture()->query();
$query = array_merge(is_array($requestQuery) ? $requestQuery : [], $baseQuery);
unset($query['page']);

$link = static function (int $p) use ($baseHost, $basePath, $query): string {
    $q = $query;
    if ($p > 1) {
        $q['page'] = $p;
    }
    $qs = $q === [] ? '' : '?' . http_build_query($q);
    return $baseHost !== '' ? $baseHost . $basePath . $qs : url($basePath) . $qs;
};

// Numaralı pencere: ilk, son ve geçerli sayfanın 2 yanı.
$numbers = [];
for ($i = 1; $i <= $pages; $i++) {
    if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
        $numbers[] = $i;
    }
}
?>
<nav class="my-4" aria-label="Sayfalama">
    <!-- Mobil: kompakt -->
    <ul class="pagination justify-content-center align-items-center mb-0 d-md-none">
        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
<?php if ($page > 1): ?>
            <a class="page-link" href="<?= e($link($page - 1)) ?>" rel="prev" aria-label="Önceki sayfa">&lsaquo; Önceki</a>
<?php else: ?>
            <span class="page-link" aria-disabled="true">&lsaquo; Önceki</span>
<?php endif; ?>
        </li>
        <li class="page-item disabled"><span class="page-link" aria-current="page">Sayfa <?= e((string) $page) ?> / <?= e((string) $pages) ?></span></li>
        <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
<?php if ($page < $pages): ?>
            <a class="page-link" href="<?= e($link($page + 1)) ?>" rel="next" aria-label="Sonraki sayfa">Sonraki &rsaquo;</a>
<?php else: ?>
            <span class="page-link" aria-disabled="true">Sonraki &rsaquo;</span>
<?php endif; ?>
        </li>
    </ul>

    <!-- Masaüstü: numaralı -->
    <ul class="pagination justify-content-center mb-0 d-none d-md-flex">
        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
<?php if ($page > 1): ?>
            <a class="page-link" href="<?= e($link($page - 1)) ?>" rel="prev" aria-label="Önceki sayfa">&lsaquo;</a>
<?php else: ?>
            <span class="page-link" aria-disabled="true">&lsaquo;</span>
<?php endif; ?>
        </li>
<?php $prev = 0; foreach ($numbers as $n): ?>
<?php if ($prev !== 0 && $n - $prev > 1): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
<?php endif; ?>
        <li class="page-item<?= $n === $page ? ' active' : '' ?>">
<?php if ($n === $page): ?>
            <span class="page-link" aria-current="page"><?= e((string) $n) ?></span>
<?php else: ?>
            <a class="page-link" href="<?= e($link($n)) ?>" aria-label="Sayfa <?= e((string) $n) ?>"><?= e((string) $n) ?></a>
<?php endif; ?>
        </li>
<?php $prev = $n; endforeach; ?>
        <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
<?php if ($page < $pages): ?>
            <a class="page-link" href="<?= e($link($page + 1)) ?>" rel="next" aria-label="Sonraki sayfa">&rsaquo;</a>
<?php else: ?>
            <span class="page-link" aria-disabled="true">&rsaquo;</span>
<?php endif; ?>
        </li>
    </ul>
<?php if (isset($pager['total'])): ?>
    <p class="text-center text-muted small mt-2 mb-0"><?= e((string) (int) $pager['total']) ?> kayıt</p>
<?php endif; ?>
</nav>
