<?php
/**
 * admin/products.php
 * Bahar Yöresel - Ürün Listeleme Sayfası
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

checkAdminLogin();

// --- SAYFALAMA AYARLARI ---
$perPage = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $perPage;

try {
    // Toplam ürün sayısını al (sayfalama hesabı için)
    $totalStmt = $db->query("SELECT COUNT(*) AS toplam FROM products");
    $toplamUrun = (int) $totalStmt->fetch()['toplam'];
    $toplamSayfa = (int) ceil($toplamUrun / $perPage);

    // Ürünleri kategori adıyla birlikte çek (category_id yanında okunabilir isim için LEFT JOIN)
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.category_id, c.name AS category_name, p.price, p.stock, p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $urunler = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Ürün listeleme hatası: ' . $e->getMessage());
    $urunler = [];
    $toplamSayfa = 1;
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold h3 mb-0">Ürünler</h1>
    <a href="/admin/product_add.php" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> Yeni Ürün Ekle
    </a>
</div>

<?php if (empty($urunler)): ?>

    <div class="alert alert-info">Henüz kayıtlı ürün bulunmuyor.</div>

<?php else: ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Ürün Adı</th>
                    <th>Kategori</th>
                    <th>Fiyat</th>
                    <th>Stok</th>
                    <th>Oluşturulma Tarihi</th>
                    <th class="text-end">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($urunler as $urun): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($urun['id']); ?></td>
                        <td><?php echo htmlspecialchars($urun['name']); ?></td>
                        <td>
                            <?php echo $urun['category_name']
                                ? htmlspecialchars($urun['category_name'])
                                : '<span class="text-secondary">Kategorisiz</span>'; ?>
                        </td>
                        <td><?php echo number_format((float) $urun['price'], 2, ',', '.'); ?> ₺</td>
                        <td>
                            <?php if ((int) $urun['stock'] <= 0): ?>
                                <span class="badge bg-danger">Tükendi</span>
                            <?php else: ?>
                                <span class="badge bg-success"><?php echo (int) $urun['stock']; ?> adet</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($urun['created_at'])); ?></td>
                        <td class="text-end">
                            <a href="/admin/product_edit.php?id=<?php echo (int) $urun['id']; ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-square"></i> Düzenle
                            </a>
                            <a href="/admin/product_delete.php?id=<?php echo (int) $urun['id']; ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Bu ürünü silmek istediğinize emin misiniz?');">
                                <i class="bi bi-trash"></i> Sil
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- SAYFALAMA -->
    <?php if ($toplamSayfa > 1): ?>
        <nav aria-label="Sayfalama">
            <ul class="pagination justify-content-center">

                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Önceki</a>
                </li>

                <?php for ($i = 1; $i <= $toplamSayfa; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?php echo ($page >= $toplamSayfa) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Sonraki</a>
                </li>

            </ul>
        </nav>
    <?php endif; ?>
    <!-- /SAYFALAMA -->

<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
?>