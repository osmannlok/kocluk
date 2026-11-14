<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Services\Database;

class UserController extends AdminController
{
    public function index(): void
    {
        $page = isset($_GET['sayfa']) ? max(1, (int) $_GET['sayfa']) : 1;
        $perPage = (require BASE_PATH . '/config/app.php')['pagination']['per_page'];
        $offset = ($page - 1) * $perPage;

        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT u.id, u.name, u.email, u.role_id, u.is_active, u.created_at, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        $countStmt = $db->prepare('SELECT COUNT(id) AS total FROM users');
        $countStmt->execute();
        $totalUsers = (int) $countStmt->fetch()['total'];
        $totalPages = (int) ceil($totalUsers / $perPage);

        $title = 'Kullanıcı Yönetimi - Admin Panel';
        $description = 'Kullanıcıları listele ve yönet.';

        require BASE_PATH . '/views/admin/users/index.php';
    }
}