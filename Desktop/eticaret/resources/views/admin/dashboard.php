<?php
/**
 * resources/views/admin/dashboard.php
 * Dashboard: KPI kartları, 14 günlük grafik, bekleyen işler, son siparişler, en çok satanlar, kritik stok.
 * Değişkenler: $stats, $chartJson, $topProducts, $lowStock, $recentOrders.
 * Ciro alanları yalnızca $stats['flags']['report'] true ise çizilir.
 */

$flags = $stats['flags'];

$statusLabels = [
    'pending_payment' => 'Ödeme Bekliyor',
    'paid' => 'Ödendi',
    'preparing' => 'Hazırlanıyor',
    'shipped' => 'Kargoda',
    'delivered' => 'Teslim Edildi',
    'cancelled' => 'İptal',
    'refunded' => 'İade',
];

$fmtDate = static function (mixed $value): string {
    $text = is_string($value) ? $value : '';
    $time = $text !== '' ? strtotime($text) : false;

    return $time !== false ? date('d.m.Y H:i', $time) : '-';
};

// Bekleyen işler: [etiket, değer, bağlantı, vurgu sınıfı]
$tasks = [];
if ($stats['orders'] !== null) {
    $tasks[] = ['Bekleyen ödeme', $stats['orders']['pending_payment'], '/yonetim/siparisler?status=pending_payment', 'warning'];
    $tasks[] = ['Hazırlanacak sipariş', $stats['orders']['to_prepare'], '/yonetim/siparisler?status=paid', 'info'];
    $tasks[] = ['Kargoda', $stats['orders']['shipped'], '/yonetim/siparisler?status=shipped', 'purple'];
}
if ($stats['pending_bank'] !== null) {
    $tasks[] = ['Onay bekleyen havale', $stats['pending_bank'], '/yonetim/siparisler?status=pending_payment&payment_method=bank_transfer', 'warning'];
}
if ($stats['low_stock'] !== null) {
    $tasks[] = ['Kritik stok', $stats['low_stock'], '/yonetim/stok?low=1', 'danger'];
}
if ($stats['new_messages'] !== null) {
    $tasks[] = ['Yeni mesaj', $stats['new_messages'], '/yonetim/mesajlar?status=new', 'primary'];
}
if ($stats['new_users'] !== null) {
    $tasks[] = ['Yeni üye (7 gün)', $stats['new_users'], '/yonetim/musteriler', 'success'];
}

$nothingToShow = !$flags['report'] && !$flags['orders'] && !$flags['stock'] && $tasks === [];
?>
<div class="page-header">
    <h1 class="h4">Dashboard</h1>
    <span class="text-muted small"><?= e(date('d.m.Y')) ?></span>
</div>

<?php if ($nothingToShow): ?>
    <div class="admin-card">
        <p class="empty-state mb-0">Bu sayfada gösterilecek veri için yetkiniz bulunmuyor.</p>
    </div>
<?php endif; ?>

<?php if ($flags['report'] && $stats['revenue'] !== null): ?>
    <section aria-labelledby="revenueHeading" class="mb-4">
        <h2 class="h6 text-muted text-uppercase mb-2" id="revenueHeading">Ciro</h2>
        <div class="row g-3">
            <div class="col-6 col-md-4 col-xl">
                <div class="kpi-card kpi-accent-primary">
                    <span class="kpi-label">Bugün</span>
                    <span class="kpi-value"><?= e(money($stats['revenue']['today'])) ?></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="kpi-card">
                    <span class="kpi-label">Dün</span>
                    <span class="kpi-value"><?= e(money($stats['revenue']['yesterday'])) ?></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="kpi-card">
                    <span class="kpi-label">Son 7 gün</span>
                    <span class="kpi-value"><?= e(money($stats['revenue']['last7'])) ?></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="kpi-card">
                    <span class="kpi-label">Son 30 gün</span>
                    <span class="kpi-value"><?= e(money($stats['revenue']['last30'])) ?></span>
                    <span class="kpi-sub"><?= e((string) $stats['revenue']['orders_30']) ?> sipariş</span>
                </div>
            </div>
            <div class="col-12 col-md-4 col-xl">
                <div class="kpi-card">
                    <span class="kpi-label">Ortalama sepet (30 gün)</span>
                    <span class="kpi-value"><?= e(money((string) $stats['avg_basket'])) ?></span>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($tasks !== []): ?>
    <section aria-labelledby="tasksHeading" class="mb-4">
        <h2 class="h6 text-muted text-uppercase mb-2" id="tasksHeading">Bekleyen işler</h2>
        <div class="row g-3">
            <?php foreach ($tasks as $task): ?>
                <div class="col-6 col-md-4 col-xl-3">
                    <a class="kpi-card kpi-accent-<?= e($task[3]) ?>" href="<?= e(url($task[2])) ?>">
                        <span class="kpi-label"><?= e($task[0]) ?></span>
                        <span class="kpi-value"><?= e((string) $task[1]) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($flags['report']): ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <section class="admin-card" aria-labelledby="chartHeading">
                <div class="admin-card-header"><h2 class="h6 mb-0" id="chartHeading">Son 14 gün satış</h2></div>
                <div class="admin-card-body">
                    <div class="chart-box">
                        <canvas id="salesChart" data-chart="<?= e($chartJson) ?>" role="img" aria-label="Son 14 günlük ciro ve sipariş adedi grafiği"></canvas>
                    </div>
                    <p class="empty-state d-none mb-0" id="salesChartEmpty">Bu dönemde satış verisi yok.</p>
                </div>
            </section>
        </div>
        <div class="col-12 col-lg-4">
            <section class="admin-card" aria-labelledby="topHeading">
                <div class="admin-card-header"><h2 class="h6 mb-0" id="topHeading">En çok satanlar <span class="text-muted fw-normal">(30 gün)</span></h2></div>
                <div class="admin-card-body flush">
                    <?php if ($topProducts === []): ?>
                        <p class="empty-state mb-0">Henüz satış yok.</p>
                    <?php else: ?>
                        <table class="table admin-table table-cards">
                            <thead>
                            <tr><th scope="col">Ürün</th><th scope="col" class="text-end">Adet</th><th scope="col" class="text-end">Ciro</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($topProducts as $row): ?>
                                <tr>
                                    <td data-label="Ürün">
                                        <?= e($row['product_name']) ?>
                                        <?php if ($row['variant_name'] !== ''): ?><span class="text-muted small d-block"><?= e($row['variant_name']) ?></span><?php endif; ?>
                                    </td>
                                    <td data-label="Adet" class="text-end"><?= e((string) $row['total_quantity']) ?></td>
                                    <td data-label="Ciro" class="text-end"><?= e(money($row['total_revenue'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
<?php endif; ?>

<?php if ($flags['orders'] || $flags['stock']): ?>
    <div class="row g-3">
        <?php if ($flags['orders']): ?>
            <div class="col-12 <?= $flags['stock'] ? 'col-lg-8' : '' ?>">
                <section class="admin-card" aria-labelledby="recentHeading">
                    <div class="admin-card-header">
                        <h2 class="h6 mb-0" id="recentHeading">Son siparişler</h2>
                        <a class="small" href="<?= e(url('/yonetim/siparisler')) ?>">Tümü</a>
                    </div>
                    <div class="admin-card-body flush">
                        <?php if ($recentOrders === []): ?>
                            <p class="empty-state mb-0">Henüz sipariş yok.</p>
                        <?php else: ?>
                            <table class="table admin-table table-hover table-cards">
                                <thead>
                                <tr>
                                    <th scope="col">Sipariş No</th>
                                    <th scope="col">Müşteri</th>
                                    <th scope="col" class="text-end">Tutar</th>
                                    <th scope="col">Durum</th>
                                    <th scope="col">Tarih</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <?php
                                    $orderNo = (string) ($order['order_number'] ?? '');
                                    $status = (string) ($order['status'] ?? '');
                                    $customer = trim((string) ($order['first_name'] ?? '') . ' ' . (string) ($order['last_name'] ?? ''));
                                    if ($customer === '') {
                                        $customer = (string) ($order['email'] ?? '');
                                    }
                                    ?>
                                    <tr>
                                        <td data-label="Sipariş No">
                                            <?php if ($orderNo !== ''): ?>
                                                <a href="<?= e(url('/yonetim/siparisler/' . rawurlencode($orderNo))) ?>"><?= e($orderNo) ?></a>
                                            <?php else: ?>-<?php endif; ?>
                                        </td>
                                        <td data-label="Müşteri"><?= e($customer !== '' ? $customer : '-') ?></td>
                                        <td data-label="Tutar" class="text-end"><?= e(money((string) ($order['grand_total'] ?? '0'))) ?></td>
                                        <td data-label="Durum"><span class="status-badge status-<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span></td>
                                        <td data-label="Tarih"><?= e($fmtDate($order['created_at'] ?? null)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>

        <?php if ($flags['stock']): ?>
            <div class="col-12 <?= $flags['orders'] ? 'col-lg-4' : '' ?>">
                <section class="admin-card" aria-labelledby="lowStockHeading">
                    <div class="admin-card-header">
                        <h2 class="h6 mb-0" id="lowStockHeading">Kritik stok</h2>
                        <a class="small" href="<?= e(url('/yonetim/stok?low=1')) ?>">Tümü</a>
                    </div>
                    <div class="admin-card-body flush">
                        <?php if ($lowStock === []): ?>
                            <p class="empty-state mb-0">Kritik stokta ürün yok.</p>
                        <?php else: ?>
                            <table class="table admin-table table-cards">
                                <thead>
                                <tr><th scope="col">Ürün</th><th scope="col" class="text-end">Stok</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($lowStock as $row): ?>
                                    <?php $qty = (int) ($row['stock_quantity'] ?? 0); ?>
                                    <tr>
                                        <td data-label="Ürün">
                                            <?= e((string) ($row['product_name'] ?? '')) ?>
                                            <span class="text-muted small d-block"><?= e(trim((string) ($row['variant_name'] ?? '') . ' ' . (string) ($row['sku'] ?? ''))) ?></span>
                                        </td>
                                        <td data-label="Stok" class="text-end">
                                            <span class="badge <?= $qty <= 0 ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= e((string) $qty) ?></span>
                                            <?php if (isset($row['low_stock_threshold'])): ?>
                                                <span class="text-muted small">/ <?= e((string) $row['low_stock_threshold']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>