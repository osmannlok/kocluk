<?php
// resources/views/admin/orders/index.php
// Girdi: $orders, $pager, $filters, $tab, $statusCounts, $bankPending, $statuses, $methods, $paymentStates, $title
declare(strict_types=1);

$statusLabels = [
    'pending_payment' => 'Ödeme Bekliyor',
    'paid'            => 'Ödendi',
    'preparing'       => 'Hazırlanıyor',
    'shipped'         => 'Kargoda',
    'delivered'       => 'Teslim Edildi',
    'cancelled'       => 'İptal',
    'refunded'        => 'İade',
];
$methodLabels = [
    'credit_card'   => 'Kredi/Banka Kartı',
    'bank_transfer' => 'Havale/EFT',
    'cod'           => 'Kapıda Ödeme',
];
$paymentLabels = [
    'pending'  => 'Bekliyor',
    'paid'     => 'Ödendi',
    'failed'   => 'Başarısız',
    'refunded' => 'İade Edildi',
];
$paymentBadge = [
    'pending'  => 'text-bg-warning',
    'paid'     => 'text-bg-success',
    'failed'   => 'text-bg-danger',
    'refunded' => 'text-bg-secondary',
];

$tabs = [
    'all'          => ['label' => 'Tümü', 'count' => null],
    'bank_pending' => ['label' => 'Ödeme bekleyen havale', 'count' => (int) $bankPending],
    'to_prepare'   => ['label' => 'Hazırlanacak', 'count' => (int) ($statusCounts['paid'] ?? 0)],
    'shipped'      => ['label' => 'Kargoda', 'count' => (int) ($statusCounts['shipped'] ?? 0)],
];

// Dışa aktarma bağlantısı geçerli filtreleri ve sekmeyi taşır.
$exportQuery = array_filter($filters, static fn ($v): bool => $v !== '');
$exportQuery['tab'] = $tab;
$exportUrl = url('/yonetim/siparisler/disa-aktar') . '?' . http_build_query($exportQuery);

$fmtDate = static function (mixed $value): string {
    $ts = is_string($value) ? strtotime($value) : false;
    return $ts === false ? '' : date('d.m.Y H:i', $ts);
};
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title ?? 'Siparişler') ?></h1>
    <?php if (can('report.view')): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e($exportUrl) ?>">
            <i class="bi bi-download" aria-hidden="true"></i> CSV dışa aktar
        </a>
    <?php endif; ?>
</div>

<ul class="nav nav-tabs mb-3" aria-label="Sipariş sekmeleri">
    <?php foreach ($tabs as $key => $t): ?>
        <li class="nav-item">
            <a class="nav-link<?= $tab === $key ? ' active' : '' ?>"
               href="<?= e(url('/yonetim/siparisler') . ($key === 'all' ? '' : '?tab=' . $key)) ?>"
               <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                <?= e($t['label']) ?>
                <?php if ($t['count'] !== null && $t['count'] > 0): ?>
                    <span class="badge text-bg-secondary"><?= e((string) $t['count']) ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" action="<?= e(url('/yonetim/siparisler')) ?>" class="filter-bar admin-card mb-3">
    <div class="admin-card-body">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label" for="f-q">Ara</label>
                <input type="search" class="form-control" id="f-q" name="q" maxlength="100"
                       placeholder="Sipariş no, ad, e-posta" value="<?= e($filters['q'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label" for="f-status">Durum</label>
                <select class="form-select" id="f-status" name="status"<?= $tab !== 'all' ? ' disabled' : '' ?>>
                    <option value="">Tümü</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= e($s) ?>"<?= ($filters['status'] ?? '') === $s ? ' selected' : '' ?>>
                            <?= e($statusLabels[$s] ?? $s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label" for="f-method">Ödeme yöntemi</label>
                <select class="form-select" id="f-method" name="payment_method"<?= $tab === 'bank_pending' ? ' disabled' : '' ?>>
                    <option value="">Tümü</option>
                    <?php foreach ($methods as $m): ?>
                        <option value="<?= e($m) ?>"<?= ($filters['payment_method'] ?? '') === $m ? ' selected' : '' ?>>
                            <?= e($methodLabels[$m] ?? $m) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label" for="f-pstatus">Ödeme durumu</label>
                <select class="form-select" id="f-pstatus" name="payment_status">
                    <option value="">Tümü</option>
                    <?php foreach ($paymentStates as $p): ?>
                        <option value="<?= e($p) ?>"<?= ($filters['payment_status'] ?? '') === $p ? ' selected' : '' ?>>
                            <?= e($paymentLabels[$p] ?? $p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-1">
                <label class="form-label" for="f-from">Başlangıç</label>
                <input type="date" class="form-control" id="f-from" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-4 col-lg-1">
                <label class="form-label" for="f-to">Bitiş</label>
                <input type="date" class="form-control" id="f-to" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-12 col-lg-1 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Filtrele</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/siparisler') . ($tab === 'all' ? '' : '?tab=' . $tab)) ?>"
                   aria-label="Filtreleri temizle"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            </div>
        </div>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-body flush">
        <?php if (empty($orders)): ?>
            <div class="empty-state p-4 text-center text-muted">
                <i class="bi bi-inbox fs-1" aria-hidden="true"></i>
                <p class="mb-0">Kriterlere uyan sipariş bulunamadı.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-cards">
                    <thead>
                        <tr>
                            <th scope="col">Sipariş No</th>
                            <th scope="col">Tarih</th>
                            <th scope="col">Müşteri</th>
                            <th scope="col" class="text-end">Tutar</th>
                            <th scope="col">Ödeme</th>
                            <th scope="col">Durum</th>
                            <th scope="col" class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <?php
                        $oid = (int) $o['id'];
                        $st = (string) ($o['status'] ?? '');
                        $pm = (string) ($o['payment_method'] ?? '');
                        $ps = (string) ($o['payment_status'] ?? '');
                        $customer = trim((string) ($o['first_name'] ?? '') . ' ' . (string) ($o['last_name'] ?? ''));
                        ?>
                        <tr>
                            <td data-label="Sipariş No">
                                <a class="fw-semibold" href="<?= e(url('/yonetim/siparisler/' . $oid)) ?>">
                                    <?= e((string) ($o['order_number'] ?? ('#' . $oid))) ?>
                                </a>
                            </td>
                            <td data-label="Tarih"><?= e($fmtDate($o['created_at'] ?? null)) ?></td>
                            <td data-label="Müşteri">
                                <?= e($customer) ?>
                                <?php if (!empty($o['email'])): ?>
                                    <div class="small text-muted"><?= e((string) $o['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Tutar" class="text-end"><?= e(money((string) ($o['grand_total'] ?? '0.00'))) ?></td>
                            <td data-label="Ödeme">
                                <div><?= e($methodLabels[$pm] ?? $pm) ?></div>
                                <span class="badge <?= e($paymentBadge[$ps] ?? 'text-bg-light') ?>"><?= e($paymentLabels[$ps] ?? $ps) ?></span>
                            </td>
                            <td data-label="Durum">
                                <span class="status-badge status-<?= e($st) ?>"><?= e($statusLabels[$st] ?? $st) ?></span>
                            </td>
                            <td data-label="İşlemler" class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/yonetim/siparisler/' . $oid)) ?>">Görüntüle</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/yonetim/siparisler/' . $oid . '/yazdir')) ?>"
                                   target="_blank" rel="noopener noreferrer" aria-label="Paket fişini yazdır">
                                    <i class="bi bi-printer" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= \App\Core\View::partial('partials.admin-pagination', ['pager' => $pager]) ?>