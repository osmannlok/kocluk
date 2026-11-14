<?php
/**
 * admin/announcements.php
 * Bahar Yöresel - Duyuru Listeleme Sayfası
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

try {
    $stmt = $db->query("
        SELECT id, title, publish_date, is_published
        FROM announcements
        ORDER BY publish_date DESC, created_at DESC
    ");
    $duyurular = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Duyuru listeleme hatası: ' . $e->getMessage());
    $duyurular = [];
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold h3 mb-0">Duyurular</h1>
    <a href="/admin/announcement_add.php" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> Yeni Duyuru Ekle
    </a>
</div>

<?php if (empty($duyurular)): ?>

    <div class="alert alert-info">Henüz kayıtlı duyuru bulunmuyor.</div>

<?php else: ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>Yayın Tarihi</th>
                    <th>Durum</th>
                    <th class="text-end">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($duyurular as $duyuru): ?>
                    <?php
                        $bugun = date('Y-m-d');
                        $henuzYayinlanmadi = !empty($duyuru['publish_date']) && $duyuru['publish_date'] > $bugun;
                    ?>
                    <tr>
                        <td><?php echo (int) $duyuru['id']; ?></td>
                        <td><?php echo htmlspecialchars($duyuru['title']); ?></td>
                        <td><?php echo $duyuru['publish_date'] ? date('d.m.Y', strtotime($duyuru['publish_date'])) : '—'; ?></td>
                        <td>
                            <?php if (!$duyuru['is_published']): ?>
                                <span class="badge bg-secondary">Taslak</span>
                            <?php elseif ($henuzYayinlanmadi): ?>
                                <span class="badge bg-warning text-dark">Zamanlanmış</span>
                            <?php else: ?>
                                <span class="badge bg-success">Yayında</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="/admin/announcement_edit.php?id=<?php echo (int) $duyuru['id']; ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-square"></i> Düzenle
                            </a>
                            <a href="/admin/announcement_delete.php?id=<?php echo (int) $duyuru['id']; ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Bu duyuruyu silmek istediğinize emin misiniz?');">
                                <i class="bi bi-trash"></i> Sil
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
?>