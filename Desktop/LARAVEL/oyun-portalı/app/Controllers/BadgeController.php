<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Badge;

class BadgeController
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $badgeModel = new Badge();
        $badges = $badgeModel->getByUser((int) $user['id']);

        $title = 'Rozetlerim - Eğitim Oyun Portalı';
        $description = 'Kazandığınız rozetleri buradan görüntüleyin.';

        require BASE_PATH . '/views/profile/badges.php';
    }

    private function requireAuth(): array
    {
        $user = $_SESSION['user'] ?? null;

        if ($user === null) {
            header('Location: /giris');
            exit;
        }

        return $user;
    }
}