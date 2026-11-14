<?php
// resources/views/admin/menus/index.php
/**
 * Girdi: $title, $menus (Menu::allWithItemCounts satırları: id, name, location, is_active, öğe sayısı).
 * Güvenlik: tüm çıktı e() ile; bu sayfada yazma işlemi yoktur; satır içi betik/stil yok.
 *
 * @var array<int,array<string,mixed>> $menus
 */
$locationLabels = [
    'header' => 'Üst menü (header)',
    'footer_1' => 'Alt bilgi 1',
    'footer_2' => 'Alt bilgi 2',
    'mobile' => 'Mobil menü',
];
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
</div>

<div class="admin-card">
    <div class="admin-card-body flush">
        <?php if ($menus === []): ?>
            <div class="empty-state p-4 text-center text-muted">Tanımlı menü yok.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-cards">
                    <thead>
                        <tr>
                            <th scope="col">Menü</th>
                            <th scope="col">Konum</th>
                            <th scope="col">Öğe sayısı</th>
                            <th scope="col">Durum</th>
                            <th scope="col" class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($menus as $m): ?>
                        <?php
                        $id = (int) $m['id'];
                        $location = (string) ($m['location'] ?? '');
                        $count = (int) ($m['item_count'] ?? $m['items_count'] ?? $m['count'] ?? 0);
                        $isActive = (int) ($m['is_active'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td data-label="Menü" class="fw-semibold"><?= e($m['name']) ?></td>
                            <td data-label="Konum">
                                <?= e($locationLabels[$location] ?? $location) ?>
                                <div class="small text-muted"><code><?= e($location) ?></code></div>
                            </td>
                            <td data-label="Öğe sayısı"><?= $count ?></td>
                            <td data-label="Durum">
                                <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= $isActive ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </td>
                            <td data-label="İşlem" class="text-end">
                                <a href="<?= e(url('/yonetim/menuler/' . $id)) ?>" class="btn btn-sm btn-outline-primary">Öğeleri düzenle</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>