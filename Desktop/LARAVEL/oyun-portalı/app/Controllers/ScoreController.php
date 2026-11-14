<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Score;

class ScoreController
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $scoreModel = new Score();
        $scores = $scoreModel->getByUser((int) $user['id']);

        $title = 'Skorlarım - Eğitim Oyun Portalı';
        $description = 'Oyun skorlarınızı buradan görüntüleyin.';

        require BASE_PATH . '/views/profile/scores.php';
    }

    public function store(string $slug): void
    {
        $user = $this->requireAuth();

        if (!$this->verifyCsrf()) {
            $this->redirect('/oyunlar/' . $slug);
        }

        $points = (int) ($_POST['score'] ?? 0);

        if ($points > 0) {
            $scoreModel = new Score();
            $scoreModel->record((int) $user['id'], $slug, $points);
        }

        $this->redirect('/oyunlar/' . $slug);
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

    private function verifyCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';

        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}