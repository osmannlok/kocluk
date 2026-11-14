<?php
/**
 * @var string $title
 * @var string $description
 */

use App\Services\Database;

$db = Database::getConnection();

$gameCount = (int) $db->query('SELECT COUNT(id) AS total FROM games WHERE is_active = 1')->fetch()['total'];
$blogCount = (int) $db->query('SELECT COUNT(id) AS total FROM blog_posts WHERE is_published = 1')->fetch()['total'];
$userCount = (int) $db->query('SELECT COUNT(id) AS total FROM users')->fetch()['total'];
$categoryCount = (int) $db->query('SELECT COUNT(id) AS total FROM categories')->fetch()['total'];

ob_start();
?>
<section class="admin-dashboard">
    <div class="admin-stats">
        <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $gameCount ?></span>
            <span class="admin-stat-label">Aktif Oyun</span>
        </div>
        <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $blogCount ?></span>
            <span class="admin-stat-label">Yayınlanmış Yazı</span>
        </div>
        <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $categoryCount ?></span>
            <span class="admin-stat-label">Kategori</span>
        </div>
        <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $userCount ?></span>
            <span class="admin-stat-label">Kayıtlı Kullanıcı</span>
        </div>
    </div>

    <div class="admin-cards">
        <a href="/admin/oyunlar" class="admin-card">
            <span class="admin-card-label">Oyunlar</span>
            <span class="admin-card-desc">Oyunları listele, ekle ve düzenle</span>
        </a>
        <a href="/admin/blog" class="admin-card">
            <span class="admin-card-label">Blog</span>
            <span class="admin-card-desc">Blog yazılarını yönet</span>
        </a>
        <a href="/admin/kategoriler" class="admin-card">
            <span class="admin-card-label">Kategoriler</span>
            <span class="admin-card-desc">Oyun kategorilerini yönet</span>
        </a>
        <a href="/admin/kullanicilar" class="admin-card">
            <span class="admin-card-label">Kullanıcılar</span>
            <span class="admin-card-desc">Kayıtlı kullanıcıları görüntüle</span>
        </a>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/admin/layouts/main.php';