<?php
// resources/views/admin/stock/index.php

/**
 * Değişkenler: $title, $rows (variant_id, product_id, product_name, variant_name, sku, stock_quantity,
 * low_stock_threshold, track_stock, is_active, status), $pager, $filters (q, durum), $lockedLow, $types.
 * Güvenlik: tüm çıktı e() ile; satır içi script/style/on* yok; düzeltme isteği JS ile CSRF başlığıyla gider.
 * SAPMA: StockAdminController 'pageScripts' geçirmediği için admin-stock.js burada, satır içi kod olmadan
 * (yalnızca src) yüklenir. JS bağlantıları: [data-stock-table], [data-variant-row][data-variant-id],
 * [data-stock-cell], [data-status-cell], [data-stock-adjust], #stockModal, [data-stock-form], [data-error-for].
 */

$statusMap = [
    'untracked' => ['label' => 'Takipsiz', 'class' => 'text-bg-secondary'],
    'out'       => ['label' => 'Stoksuz', 'class' => 'text-bg-danger'],
    'low'       => ['label' => 'Kritik', 'class' => 'text-bg-warning'],
    'ok'        => ['label' => 'Yeterli', 'class' => 'text-bg-success'],
];
$statusJson = (string) json_encode($statusMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$durum = (string) ($filters['durum'] ?? '');
$q = (string) ($filters['q'] ?? '');
$formAction = $lockedLow ? '/yonetim/stok/kritik' : '/yonetim/stok';
$hasFilter = $q !== '' || ($durum !== '' && !$lockedLow);
?>
<div class="page-header">
    <div>
        <h1 class="h4 mb-0"><?= e($title) ?></h1>
        <p class="text-muted small mb-0"><?= e((string) ($pager['total'] ?? 0)) ?> kayıt</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($lockedLow): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/stok')) ?>">
                <i class="bi bi-list-ul" aria-hidden="true"></i> Tüm stok
            </a>
        <?php else: ?>
            <a class="btn btn-outline-warning" href="<?= e(url('/yonetim/stok/kritik')) ?>">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Kritik stok
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="filter-bar" method="get" action="<?= e(url($formAction)) ?>" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-6">
            <label class="form-label small mb-1" for="stockQ">Arama (ürün, varyant, SKU)</label>
            <input type="search" class="form-control" id="stockQ" name="q" maxlength="100" value="<?= e($q) ?>">
        </div>
        <?php if (!$lockedLow): ?>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="stockDurum">Stok durumu</label>
                <select class="form-select" id="stockDurum" name="durum">
                    <option value="">Tümü</option>
                    <option value="kritik"<?= $durum === 'kritik' ? ' selected' : '' ?>>Kritik</option>
                    <option value="stoksuz"<?= $durum === 'stoksuz' ? ' selected' : '' ?>>Stoksuz</option>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filtrele
            </button>
            <?php if ($hasFilter): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url($formAction)) ?>">Temizle</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="admin-card">
    <?php if ($rows === []): ?>
        <div class="admin-card-body">
            <div class="empty-state">
                <i class="bi bi-boxes" aria-hidden="true"></i>
                <p class="mb-0"><?= ($hasFilter || $lockedLow) ? 'Koşula uyan varyant bulunamadı.' : 'Henüz varyant yok.' ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-card-body flush">
            <table class="table table-cards align-middle mb-0" data-stock-table
                   data-status="<?= e($statusJson) ?>">
                <thead>
                    <tr>
                        <th scope="col">Ürün</th>
                        <th scope="col">Varyant</th>
                        <th scope="col">SKU</th>
                        <th scope="col" class="text-end">Stok</th>
                        <th scope="col" class="text-end">Eşik</th>
                        <th scope="col">Durum</th>
                        <th scope="col" class="text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $vid = (int) $r['variant_id'];
                    $status = (string) $r['status'];
                    $badge = $statusMap[$status] ?? $statusMap['ok'];
                    $tracked = (bool) $r['track_stock'];
                    ?>
                    <tr data-variant-row data-variant-id="<?= e((string) $vid) ?>">
                        <td data-label="Ürün">
                            <a class="fw-semibold text-decoration-none"
                               href="<?= e(url('/yonetim/urunler/' . (int) $r['product_id'] . '/duzenle')) ?>">
                                <?= e((string) $r['product_name']) ?>
                            </a>
                        </td>
                        <td data-label="Varyant">
                            <?= e((string) $r['variant_name']) ?>
                            <?php if (!$r['is_active']): ?>
                                <span class="badge text-bg-light border">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="SKU"><code><?= e((string) $r['sku']) ?></code></td>
                        <td data-label="Stok" class="text-end">
                            <?php if ($tracked): ?>
                                <span data-stock-cell><?= e((string) (int) $r['stock_quantity']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Eşik" class="text-end" data-threshold-cell>
                            <?= $tracked ? e((string) (int) $r['low_stock_threshold']) : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td data-label="Durum" data-status-cell>
                            <span class="badge <?= e($badge['class']) ?>"><?= e($badge['label']) ?></span>
                        </td>
                        <td data-label="İşlemler" class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <?php if ($tracked): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#stockModal" data-stock-adjust
                                            data-variant-id="<?= e((string) $vid) ?>"
                                            data-product-name="<?= e((string) $r['product_name']) ?>"
                                            data-variant-name="<?= e((string) $r['variant_name']) ?>"
                                            data-sku="<?= e((string) $r['sku']) ?>"
                                            aria-label="<?= e($r['product_name'] . ' ' . $r['variant_name'] . ' stoğunu düzelt') ?>">
                                        <i class="bi bi-sliders" aria-hidden="true"></i> Stok düzelt
                                    </button>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= e(url('/yonetim/stok/' . $vid . '/hareketler')) ?>"
                                   aria-label="<?= e($r['product_name'] . ' ' . $r['variant_name'] . ' stok geçmişi') ?>">
                                    <i class="bi bi-clock-history" aria-hidden="true"></i> Geçmiş
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= \App\Core\View::partial('partials.admin-pagination', ['pager' => $pager]) ?>

<div class="modal fade" id="stockModal" tabindex="-1" aria-labelledby="stockModalTitle" aria-hidden="true"
     data-adjust-url="<?= e(url('/yonetim/stok/duzelt')) ?>">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= e(url('/yonetim/stok/duzelt')) ?>" data-stock-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="variant_id" value="">
            <div class="modal-header">
                <h2 class="modal-title h5" id="stockModalTitle">Stok düzelt</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1 fw-semibold" data-stock-target></p>
                <p class="text-muted small">Mevcut stok: <strong data-stock-current>0</strong></p>

                <div class="alert alert-danger py-2" role="alert" data-stock-error hidden></div>

                <div class="mb-3">
                    <label class="form-label" for="stockType">Hareket türü</label>
                    <select class="form-select" id="stockType" name="type" required>
                        <?php foreach ($types as $code => $label): ?>
                            <option value="<?= e((string) $code) ?>"><?= e((string) $label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback" data-error-for="type"></div>
                </div>

                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Miktar</legend>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="stockModeDelta" value="delta" checked>
                        <label class="form-check-label" for="stockModeDelta">Değişim (+/−)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="stockModeNew" value="new_quantity">
                        <label class="form-check-label" for="stockModeNew">Yeni miktar</label>
                    </div>
                    <div class="mt-2" data-mode-group="delta">
                        <input type="number" class="form-control" name="delta" step="1" inputmode="numeric"
                               aria-label="Değişim miktarı" placeholder="ör. 10 veya -3">
                        <div class="invalid-feedback" data-error-for="delta"></div>
                    </div>
                    <div class="mt-2" data-mode-group="new_quantity" hidden>
                        <input type="number" class="form-control" name="new_quantity" min="0" step="1" inputmode="numeric"
                               aria-label="Yeni stok miktarı" placeholder="ör. 40" disabled>
                        <div class="invalid-feedback" data-error-for="new_quantity"></div>
                    </div>
                    <div class="form-text">Sonuç: <strong data-stock-result>—</strong></div>
                </fieldset>

                <div class="mb-0">
                    <label class="form-label" for="stockNote">Not (zorunlu)</label>
                    <textarea class="form-control" id="stockNote" name="note" rows="2" maxlength="255" required></textarea>
                    <div class="invalid-feedback" data-error-for="note"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" class="btn btn-primary" data-stock-submit>
                    <i class="bi bi-check-lg" aria-hidden="true"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?= e(url('/assets/js/admin-stock.js')) ?>" defer></script>