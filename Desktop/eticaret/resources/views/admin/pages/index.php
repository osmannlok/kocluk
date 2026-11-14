<?php
// resources/views/admin/pages/index.php
/**
 * @var list<array<string,mixed>> $pages
 * @var array<string,mixed> $pager
 * @var string $status
 */
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">Sayfalar</h1>
    <a class="btn btn-primary" href="<?= e(url('/yonetim/sayfalar/yeni')) ?>">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Yeni sayfa
    </a>
</div>

<form method="get" action="<?= e(url('/yonetim/sayfalar')) ?>" class="filter-bar mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-8 col-md-4">
            <label for="filterStatus" class="form-label">Durum</label>
            <select id="filterStatus" name="status" class="form-select">
                <option value="">Tümü</option>
                <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Yayında</option>
                <option value="passive"<?= $status === 'passive' ? ' selected' : '' ?>>Yayında değil</option>
            </select>
        </div>
        <div class="col-4 col-md-2">
            <button type="submit" class="btn btn-outline-secondary w-100">Filtrele</button>
        </div>
    </div>
</form>

<div class="admin-card">
    <div class="admin-card-body flush">
        <?php if ($pages === []): ?>
            <div class="empty-state p-4 text-center text-muted">Henüz sayfa yok.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-cards align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Başlık</th>
                            <th scope="col">Bağlantı</th>
                            <th scope="col">Durum</th>
                            <th scope="col">Footer</th>
                            <th scope="col">Güncelleme</th>
                            <th scope="col" class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pages as $p): ?>
                        <?php
                        $id = (int) $p['id'];
                        $isSystem = !empty($p['system_key']);
                        $isActive = ((int) $p['is_active']) === 1;
                        ?>
                        <tr>
                            <td data-label="Başlık">
                                <span class="fw-semibold"><?= e($p['title']) ?></span>
                                <?php if ($isSystem): ?>
                                    <span class="badge text-bg-warning ms-1">Yasal sayfa</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Bağlantı">
                                <?php if ($isActive): ?>
                                    <a href="<?= e(url('/sayfa/' . $p['slug'])) ?>" target="_blank" rel="noopener noreferrer">/sayfa/<?= e($p['slug']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">/sayfa/<?= e($p['slug']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Durum">
                                <?php if ($isActive): ?>
                                    <span class="badge text-bg-success">Yayında</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Yayında değil</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Footer"><?= ((int) $p['show_in_footer']) === 1 ? 'Evet' : 'Hayır' ?></td>
                            <td data-label="Güncelleme"><?= e(date('d.m.Y H:i', (int) strtotime((string) $p['updated_at']))) ?></td>
                            <td data-label="İşlemler" class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/yonetim/sayfalar/' . $id . '/duzenle')) ?>">Düzenle</a>
                                    <form method="post" action="<?= e(url('/yonetim/sayfalar/' . $id . '/durum')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $isActive ? 'Yayından kaldır' : 'Yayına al' ?>
                                        </button>
                                    </form>
                                    <?php if (!$isSystem): ?>
                                        <form method="post" action="<?= e(url('/yonetim/sayfalar/' . $id . '/sil')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Bu sayfayı silmek istediğinize emin misiniz?">Sil</button>
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
</div>

<?= \App\Core\View::partial('partials.admin-pagination', ['pager' => $pager]) ?>