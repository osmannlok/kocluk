<?php
/**
 * @var array $users
 * @var int $totalPages
 * @var string $title
 * @var string $description
 */
ob_start();

$currentPage = isset($_GET['sayfa']) ? max(1, (int) $_GET['sayfa']) : 1;
?>
<section class="admin-table-section">
    <div class="admin-table-header">
        <p><?= count($users) ?> kullanıcı gösteriliyor</p>
    </div>

    <?php if (empty($users)): ?>
        <p class="empty-state">Henüz kullanıcı bulunmuyor.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ad Soyad</th>
                    <th>E-posta</th>
                    <th>Rol</th>
                    <th>Durum</th>
                    <th>Kayıt Tarihi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role_name']) ?></td>
                        <td>
                            <span class="status-badge <?= $user['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $user['is_active'] ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(date('d.m.Y', strtotime($user['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Sayfalama">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="/admin/kullanicilar?sayfa=<?= $i ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/admin/layouts/main.php';