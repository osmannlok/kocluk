<?php

declare(strict_types=1);

namespace App\Controllers;

class AdminController
{
    public function __construct()
    {
        $this->requireAdmin();
    }

    public function index(): void
    {
        $title = 'Yönetim Paneli - Eğitim Oyun Portalı';
        $description = 'Site yönetim paneli.';

        require BASE_PATH . '/views/admin/dashboard/index.php';
    }

    protected function requireAdmin(): void
    {
        $user = $_SESSION['user'] ?? null;

        if ($user === null || (int) $user['role_id'] !== 1) {
            header('Location: /giris');
            exit;
        }
    }
}