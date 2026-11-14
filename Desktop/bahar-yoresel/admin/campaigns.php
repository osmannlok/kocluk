<?php
/**
 * admin/campaigns.php
 * Bahar Yöresel - Kampanya Listeleme Sayfası
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

try {
    $stmt = $db->query("
        SELECT id, title, discount_type, discount_value, start_date, end_date, is_active
        FROM campaigns
        ORDER BY created_at DESC
    ");
    $kampanyalar = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Kampanya listeleme hatası: ' . $e->getMessage());
    $kampanyalar = [];
}

// discount_type için Türkçe etiket eşleşmesi
$discountTypeEtiketleri = [
    'percentage'    => 'Yüzde İndirim',
    'fixed'         => 'Sabit Tutar',
    'free_shipping' => 'Ücretsiz Kargo',
];

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold h3 mb-0">Kampanyalar</h1>
    <a href="/admin/campaign_add.php" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> Yeni Kampanya Ekle
    </a>
</div>

<?php if (empty($kampanyalar)): ?>

    <div class="alert alert-info">Henüz kayıtlı kampanya bulunmuyor.</div>

<?php else: ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>İndirim Türü</th>
                    <th>İndirim Değeri</th>
                    <th>Başlangıç</th>
                    <th>Bitiş</th>
                    <th>Durum</th>
                    <th class="text-end">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kampanyalar as $kampanya): ?>
                    <?php
                        $bugun = date('Y-m-d');
                        $tarihiGecmis = !empty($kampanya['end_date']) && $kampanya['end_date'] < $bugun;
                    ?>
                    <tr>
                        <td><?php echo (int) $kampanya['id']; ?></td>
                        <td><?php echo htmlspecialchars($kampanya['title']); ?></td>
                        <td>
                            <?php echo htmlspecialchars(
                                $discountTypeEtiketleri[$kampanya['discount_type']] ?? $kampanya['discount_type']
                            ); ?>
                        </td>
                        <td>
                            <?php if ($kampanya['discount_type'] === 'free_shipping'): ?>
                                <span class="text-secondary">—</span>
                            <?php elseif ($kampanya['discount_type'] === 'percentage'): ?>
                                %<?php echo rtrim(rtrim(number_format((float) $kampanya['discount_value'], 2, ',', '.'), '0'), ','); ?>
                            <?php else: ?>
                                <?php echo number_format((float) $kampanya['discount_value'], 2, ',', '.'); ?> ₺
                            <?php endif; ?>
                        </td>
                        <td><?php echo $kampanya['start_date'] ? date('d.m.Y', strtotime($kampanya['start_date'])) : '—'; ?></td>
                        <td><?php echo $kampanya['end_date'] ? date('d.m.Y', strtotime($kampanya['end_date'])) : '—'; ?></td>
                        <td>
                            <?php if (!$kampanya['is_active']): ?>
                                <span class="badge bg-secondary">Pasif</span>
                            <?php elseif ($tarihiGecmis): ?>
                                <span class="badge bg-warning text-dark">Süresi Doldu</span>
                            <?php else: ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="/admin/campaign_edit.php?id=<?php echo (int) $kampanya['id']; ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-square"></i> Düzenle
                            </a>
                            <a href="/admin/campaign_delete.php?id=<?php echo (int) $kampanya['id']; ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Bu kampanyayı silmek istediğinize emin misiniz?');">
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