<?php
// resources/views/admin/products/index.php

/**
 * Değişkenler: $products (Product::adminList satırları), $pager (AdminBaseController::pager çıktısı),
 * $filters (q, category_id, region_id, is_active, is_featured), $categories (Category::allFlat), $regions (Region::activeAll).
 * Güvenlik: tüm çıktı e() ile; satır içi script/style/on* yok; her değiştirici işlem CSRF'li POST formudur.
 */

$canCreate = can('product.create');
$canUpdate = can('product.update');
$canDelete = can('product.delete');
$canStock = can('stock.manage');

$selCategory = (int) ($filters['category_id'] ?? 0);
$selRegion = (int) ($filters['region_id'] ?? 0);
$selActive = (string) ($filters['is_active'] ?? '');
$selFeatured = (string) ($filters['is_featured'] ?? '');
$hasFilter = $filters !== [];
?>
<div class="page-header">
    <div>
        <h1 class="h4 mb-0">Ürünler</h1>
        <p class="text-muted small mb-0"><?= e((string) ($pager['total'] ?? 0)) ?> ürün</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($canStock): ?>
            <a class="btn btn-outline-warning" href="<?= e(url('/yonetim/stok/kritik')) ?>">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Kritik stok
            </a>
        <?php endif; ?>
        <?php if ($canCreate): ?>
            <a class="btn btn-primary" href="<?= e(url('/yonetim/urunler/olustur')) ?>">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Yeni ürün
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="filter-bar" method="get" action="<?= e(url('/yonetim/urunler')) ?>" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label small mb-1" for="filterQ">Arama (ad, slug, SKU)</label>
            <input type="search" class="form-control" id="filterQ" name="q" maxlength="100"
                   value="<?= e((string) ($filters['q'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="filterCategory">Kategori</label>
            <select class="form-select" id="filterCategory" name="category_id">
                <option value="">Tümü</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e((string) (int) $c['id']) ?>"<?= $selCategory === (int) $c['id'] ? ' selected' : '' ?>>
                        <?= e((($c['parent_id'] ?? null) !== null ? '— ' : '') . (string) $c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="filterRegion">Yöre</label>
            <select class="form-select" id="filterRegion" name="region_id">
                <option value="">Tümü</option>
                <?php foreach ($regions as $r): ?>
                    <option value="<?= e((string) (int) $r['id']) ?>"<?= $selRegion === (int) $r['id'] ? ' selected' : '' ?>>
                        <?= e((string) $r['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="filterActive">Durum</label>
            <select class="form-select" id="filterActive" name="is_active">
                <option value="">Tümü</option>
                <option value="1"<?= $selActive === '1' ? ' selected' : '' ?>>Aktif</option>
                <option value="0"<?= $selActive === '0' ? ' selected' : '' ?>>Pasif</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="filterFeatured">Öne çıkan</label>
            <select class="form-select" id="filterFeatured" name="is_featured">
                <option value="">Tümü</option>
                <option value="1"<?= $selFeatured === '1' ? ' selected' : '' ?>>Evet</option>
                <option value="0"<?= $selFeatured === '0' ? ' selected' : '' ?>>Hayır</option>
            </select>
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filtrele
            </button>
            <?php if ($hasFilter): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/urunler')) ?>">Temizle</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="admin-card">
    <?php if ($products === []): ?>
        <div class="admin-card-body">
            <div class="empty-state">
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                <p class="mb-0"><?= $hasFilter ? 'Filtreye uyan ürün bulunamadı.' : 'Henüz ürün eklenmemiş.' ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-card-body flush">
            <table class="table table-cards align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Görsel</th>
                        <th scope="col">Ürün</th>
                        <th scope="col">Kategori</th>
                        <th scope="col" class="text-end">Varyant</th>
                        <th scope="col" class="text-end">Fiyat</th>
                        <th scope="col" class="text-end">Toplam stok</th>
                        <th scope="col">Durum</th>
                        <th scope="col" class="text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <?php
                    $id = (int) $p['id'];
                    $min = $p['min_price'] ?? null;
                    $max = $p['max_price'] ?? null;
                    $stock = $p['stock_total'] ?? null;
                    $active = (bool) $p['is_active'];
                    $name = (string) $p['name'];
                    ?>
                    <tr>
                        <td data-label="Görsel">
                            <?php if (!empty($p['image_path'])): ?>
                                <img src="<?= e((string) $p['image_path']) ?>" alt="<?= e($name) ?>"
                                     class="img-thumbnail object-fit-cover" width="56" height="56" loading="lazy">
                            <?php else: ?>
                                <span class="text-muted small">Yok</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Ürün">
                            <div class="fw-semibold"><?= e($name) ?></div>
                            <div class="text-muted small"><?= e((string) $p['slug']) ?></div>
                            <?php if (!empty($p['region_name'])): ?>
                                <span class="badge text-bg-light border"><?= e((string) $p['region_name']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($p['is_featured'])): ?>
                                <span class="badge text-bg-info">Öne çıkan</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Kategori"><?= e((string) ($p['category_name'] ?? '')) ?></td>
                        <td data-label="Varyant" class="text-end"><?= e((string) (int) $p['variant_count']) ?></td>
                        <td data-label="Fiyat" class="text-end text-nowrap">
                            <?php if ($min === null): ?>
                                <span class="text-muted">—</span>
                            <?php elseif ($min === $max): ?>
                                <?= e(money($min)) ?>
                            <?php else: ?>
                                <?= e(money($min)) ?> – <?= e(money((string) $max)) ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Toplam stok" class="text-end text-nowrap">
                            <?php if ($stock === null): ?>
                                <span class="text-muted">Takipsiz</span>
                            <?php else: ?>
                                <?= e((string) (int) $stock) ?>
                                <?php if (!empty($p['low_stock'])): ?>
                                    <span class="badge text-bg-danger">Kritik</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Durum">
                            <?php if ($canUpdate): ?>
                                <form method="post" action="<?= e(url('/yonetim/urunler/' . $id . '/aktif')) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            class="btn btn-sm <?= $active ? 'btn-success' : 'btn-outline-secondary' ?>"
                                            aria-label="<?= e($name . ($active ? ' ürününü pasifleştir' : ' ürününü aktifleştir')) ?>">
                                        <i class="bi <?= $active ? 'bi-toggle-on' : 'bi-toggle-off' ?>" aria-hidden="true"></i>
                                        <?= $active ? 'Aktif' : 'Pasif' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge <?= $active ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= $active ? 'Aktif' : 'Pasif' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="İşlemler" class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <?php if ($active): ?>
                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                                       href="<?= e(url('/urun/' . rawurlencode((string) $p['slug']))) ?>"
                                       aria-label="<?= e($name . ' ürününü vitrinde gör') ?>">
                                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($canUpdate): ?>
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="<?= e(url('/yonetim/urunler/' . $id . '/duzenle')) ?>"
                                       aria-label="<?= e($name . ' ürününü düzenle') ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Düzenle
                                    </a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= e(url('/yonetim/urunler/' . $id . '/sil')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="<?= e('"' . $name . '" ürünü silinecek. Devam edilsin mi?') ?>"
                                                aria-label="<?= e($name . ' ürününü sil') ?>">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
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