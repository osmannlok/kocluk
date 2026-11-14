<?php
// resources/views/admin/stock/history.php

/**
 * Değişkenler: $title, $variant (id, product_id, product_name, name, sku, stock_quantity,
 * low_stock_threshold, track_stock), $movements (stock_movements kolonları + user_name, order_number, type_label), $pager.
 * Güvenlik: tüm çıktı e() ile; satır içi script/style/on* yok; sayfa salt okunurdur (form/POST yok).
 */

$formatDate = static function (mixed $value): string {
    $ts = strtotime((string) $value);

    return $ts === false ? '' : date('d.m.Y H:i', $ts);
};
$tracked = (bool) $variant['track_stock'];
$name = $variant['product_name'] . ' — ' . $variant['name'];
?>
<div class="page-header">
    <div>
        <h1 class="h4 mb-0">Stok hareketleri</h1>
        <p class="text-muted small mb-0"><?= e($name) ?> · <code><?= e((string) $variant['sku']) ?></code></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/urunler/' . (int) $variant['product_id'] . '/duzenle')) ?>">
            <i class="bi bi-box-seam" aria-hidden="true"></i> Ürüne git
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/stok')) ?>">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Stoka dön
        </a>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="admin-card-body">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-muted small">Mevcut stok</div>
                <div class="fs-5 fw-semibold">
                    <?= $tracked ? e((string) (int) $variant['stock_quantity']) : '<span class="text-muted">Takipsiz</span>' ?>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Kritik eşik</div>
                <div class="fs-5 fw-semibold"><?= $tracked ? e((string) (int) $variant['low_stock_threshold']) : '—' ?></div>
            </div>
            <div class="col-12 col-md-6">
                <div class="text-muted small">Durum</div>
                <?php if (!$tracked): ?>
                    <span class="badge text-bg-secondary">Takipsiz</span>
                <?php elseif ((int) $variant['stock_quantity'] <= 0): ?>
                    <span class="badge text-bg-danger">Stoksuz</span>
                <?php elseif ((int) $variant['stock_quantity'] <= (int) $variant['low_stock_threshold']): ?>
                    <span class="badge text-bg-warning">Kritik</span>
                <?php else: ?>
                    <span class="badge text-bg-success">Yeterli</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <?php if ($movements === []): ?>
        <div class="admin-card-body">
            <div class="empty-state">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                <p class="mb-0">Bu varyant için henüz stok hareketi yok.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-card-body flush">
            <table class="table table-cards align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Tarih</th>
                        <th scope="col">Tür</th>
                        <th scope="col" class="text-end">Değişim</th>
                        <th scope="col" class="text-end">Sonraki stok</th>
                        <th scope="col">Referans</th>
                        <th scope="col">Kullanıcı</th>
                        <th scope="col">Not</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movements as $m): ?>
                    <?php
                    $change = (int) $m['quantity_change'];
                    $orderNumber = $m['order_number'] ?? null;
                    ?>
                    <tr>
                        <td data-label="Tarih" class="text-nowrap"><?= e($formatDate($m['created_at'] ?? '')) ?></td>
                        <td data-label="Tür"><?= e((string) $m['type_label']) ?></td>
                        <td data-label="Değişim" class="text-end text-nowrap">
                            <span class="fw-semibold <?= $change > 0 ? 'text-success' : ($change < 0 ? 'text-danger' : '') ?>">
                                <?= e(($change > 0 ? '+' : '') . (string) $change) ?>
                            </span>
                        </td>
                        <td data-label="Sonraki stok" class="text-end"><?= e((string) (int) $m['quantity_after']) ?></td>
                        <td data-label="Referans">
                            <?php if ($orderNumber !== null && $orderNumber !== ''): ?>
                                <a href="<?= e(url('/yonetim/siparisler/' . rawurlencode((string) $orderNumber))) ?>">
                                    #<?= e((string) $orderNumber) ?>
                                </a>
                            <?php elseif (!empty($m['reference_type'])): ?>
                                <span class="text-muted">
                                    <?= e((string) $m['reference_type'] . (!empty($m['reference_id']) ? ' #' . (int) $m['reference_id'] : '')) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Kullanıcı">
                            <?= !empty($m['user_name']) ? e((string) $m['user_name']) : '<span class="text-muted">Sistem</span>' ?>
                        </td>
                        <td data-label="Not"><?= !empty($m['note']) ? e((string) $m['note']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= \App\Core\View::partial('partials.admin-pagination', ['pager' => $pager]) ?>