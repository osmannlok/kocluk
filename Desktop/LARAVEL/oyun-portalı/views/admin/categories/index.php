<?php
/**
 * @var array $categories
 * @var string $title
 * @var string $description
 */
ob_start();
?>
<section class="admin-table-section">
    <div class="admin-table-header">
        <p><?= count($categories) ?> kategori gösteriliyor</p>
    </div>

    <?php if (empty($categories)): ?>
        <p class="empty-state">Henüz kategori bulunmuyor.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ad</th>
                    <th>Slug</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= (int) $category['id'] ?></td>
                        <td><?= htmlspecialchars($category['name']) ?></td>
                        <td><?= htmlspecialchars($category['slug']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/admin/layouts/main.php';