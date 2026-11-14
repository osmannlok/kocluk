<?php
/**
 * resources/views/partials/admin-pagination.php
 * Toplam kayıt metni + sabit 25 satırlık sayfalama.
 * Değişken: $pager = AdminBaseController::pager() çıktısı {total,page,per_page,pages,path,query}.
 */

$pager = isset($pager) && is_array($pager) ? $pager : [];

$total = max(0, (int) ($pager['total'] ?? 0));
$perPage = max(1, (int) ($pager['per_page'] ?? 25));
$pages = max(1, (int) ($pager['pages'] ?? (int) ceil($total / $perPage)));
$page = min($pages, max(1, (int) ($pager['page'] ?? 1)));
$path = (string) ($pager['path'] ?? '/yonetim');
$query = is_array($pager['query'] ?? null) ? $pager['query'] : [];
unset($query['page']);

$from = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
$to = min($total, $page * $perPage);

$href = static function (int $p) use ($path, $query): string {
    $q = $query;
    if ($p > 1) {
        $q['page'] = $p;
    }
    $qs = http_build_query($q);

    return url($path) . ($qs !== '' ? '?' . $qs : '');
};

// Gösterilecek sayfalar: ilk, son ve geçerli sayfanın ±2 komşusu.
$show = array_unique(array_merge([1, $pages], range(max(1, $page - 2), min($pages, $page + 2))));
sort($show);
?>
<div class="admin-pager d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3">
    <p class="mb-0 text-muted small">
        Toplam <strong><?= e(number_format($total, 0, ',', '.')) ?></strong> kayıt
        <?php if ($total > 0): ?>
            · <?= e(number_format($from, 0, ',', '.')) ?>–<?= e(number_format($to, 0, ',', '.')) ?> arası gösteriliyor
        <?php endif; ?>
        · Sayfa başına <?= e((string) $perPage) ?> kayıt
    </p>

    <?php if ($pages > 1): ?>
        <nav aria-label="Sayfalama">
            <ul class="pagination pagination-sm mb-0 flex-wrap">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <?php if ($page > 1): ?>
                        <a class="page-link" href="<?= e($href($page - 1)) ?>" rel="prev" aria-label="Önceki sayfa">&laquo;</a>
                    <?php else: ?>
                        <span class="page-link" aria-hidden="true">&laquo;</span>
                    <?php endif; ?>
                </li>

                <?php $previous = 0; ?>
                <?php foreach ($show as $p): ?>
                    <?php if ($previous !== 0 && $p - $previous > 1): ?>
                        <li class="page-item disabled"><span class="page-link" aria-hidden="true">…</span></li>
                    <?php endif; ?>
                    <li class="page-item<?= $p === $page ? ' active' : '' ?>">
                        <a class="page-link" href="<?= e($href($p)) ?>"<?= $p === $page ? ' aria-current="page"' : '' ?> aria-label="Sayfa <?= e((string) $p) ?>"><?= e((string) $p) ?></a>
                    </li>
                    <?php $previous = $p; ?>
                <?php endforeach; ?>

                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <?php if ($page < $pages): ?>
                        <a class="page-link" href="<?= e($href($page + 1)) ?>" rel="next" aria-label="Sonraki sayfa">&raquo;</a>
                    <?php else: ?>
                        <span class="page-link" aria-hidden="true">&raquo;</span>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>