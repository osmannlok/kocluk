<?php
// resources/views/admin/categories/index.php

/**
 * Değişkenler: $title, $categories (CategoryAdminController::flatten satırları: id, name, slug, parent_id, image_path,
 * sort_order, is_active, depth, child_count, is_first, is_last; ağaç sırasında).
 * Güvenlik: tüm çıktı e() ile; satır içi script/style/on* yok; her değiştirici işlem CSRF'li POST formudur.
 */

$canDelete = can('category.manage') && can('product.delete');
$indent = ['', 'ps-3', 'ps-4', 'ps-5'];
?>
<div class="page-header">
    <div>
        <h1 class="h4 mb-0">Kategoriler</h1>
        <p class="text-muted small mb-0"><?= e((string) count($categories)) ?> kategori</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/yonetim/kategoriler/olustur')) ?>">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Yeni kategori
    </a>
</div>

<div class="admin-card">
    <?php if ($categories === []): ?>
        <div class="admin-card-body">
            <div class="empty-state">
                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                <p class="mb-0">Henüz kategori eklenmemiş.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-card-body flush">
            <table class="table table-cards align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Kategori</th>
                        <th scope="col" class="text-end">Alt kategori</th>
                        <th scope="col">Sıra</th>
                        <th scope="col">Durum</th>
                        <th scope="col" class="text-end">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <?php
                    $id = (int) $c['id'];
                    $name = (string) $c['name'];
                    $depth = (int) $c['depth'];
                    $active = (bool) $c['is_active'];
                    $pad = $indent[min($depth, 3)];
                    ?>
                    <tr>
                        <td data-label="Kategori" class="<?= e($pad) ?>">
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($c['image_path'])): ?>
                                    <img src="<?= e((string) $c['image_path']) ?>" alt="<?= e($name) ?>"
                                         class="img-thumbnail object-fit-cover" width="40" height="40" loading="lazy">
                                <?php endif; ?>
                                <div>
                                    <?php if ($depth > 0): ?><span class="text-muted" aria-hidden="true">└ </span><?php endif; ?>
                                    <span class="fw-semibold"><?= e($name) ?></span>
                                    <div class="text-muted small"><?= e((string) $c['slug']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Alt kategori" class="text-end"><?= e((string) (int) $c['child_count']) ?></td>
                        <td data-label="Sıra">
                            <form method="post" action="<?= e(url('/yonetim/kategoriler/sirala')) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                                <div class="btn-group btn-group-sm" role="group" aria-label="<?= e($name . ' sırası') ?>">
                                    <button type="submit" name="direction" value="up" class="btn btn-outline-secondary"
                                            aria-label="<?= e($name . ' yukarı taşı') ?>"<?= !empty($c['is_first']) ? ' disabled' : '' ?>>
                                        <i class="bi bi-arrow-up" aria-hidden="true"></i>
                                    </button>
                                    <button type="submit" name="direction" value="down" class="btn btn-outline-secondary"
                                            aria-label="<?= e($name . ' aşağı taşı') ?>"<?= !empty($c['is_last']) ? ' disabled' : '' ?>>
                                        <i class="bi bi-arrow-down" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </form>
                        </td>
                        <td data-label="Durum">
                            <form method="post" action="<?= e(url('/yonetim/kategoriler/' . $id . '/aktif')) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit"
                                        class="btn btn-sm <?= $active ? 'btn-success' : 'btn-outline-secondary' ?>"
                                        aria-label="<?= e($name . ($active ? ' kategorisini pasifleştir' : ' kategorisini aktifleştir')) ?>">
                                    <i class="bi <?= $active ? 'bi-toggle-on' : 'bi-toggle-off' ?>" aria-hidden="true"></i>
                                    <?= $active ? 'Aktif' : 'Pasif' ?>
                                </button>
                            </form>
                        </td>
                        <td data-label="İşlemler" class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= e(url('/yonetim/kategoriler/olustur?parent_id=' . $id)) ?>"
                                   aria-label="<?= e($name . ' altına kategori ekle') ?>">
                                    <i class="bi bi-node-plus" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="<?= e(url('/yonetim/kategoriler/' . $id . '/duzenle')) ?>"
                                   aria-label="<?= e($name . ' kategorisini düzenle') ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i> Düzenle
                                </a>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= e(url('/yonetim/kategoriler/' . $id . '/sil')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <?php if ((int) $c['child_count'] > 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                    title="Alt kategorisi olan kategori silinemez"
                                                    aria-label="<?= e($name . ' silinemez: alt kategorisi var') ?>">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    data-confirm="<?= e('"' . $name . '" kategorisi silinecek. Devam edilsin mi?') ?>"
                                                    aria-label="<?= e($name . ' kategorisini sil') ?>">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
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