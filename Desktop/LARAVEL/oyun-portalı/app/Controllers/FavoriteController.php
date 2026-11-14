<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Favorite;

class FavoriteController
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $favoriteModel = new Favorite();
        $favorites = $favoriteModel->getByUser((int) $user['id']);

        $title = 'Favorilerim - Eğitim Oyun Portalı';
        $description = 'Favori oyunlarınızı buradan görüntüleyin.';

        require BASE_PATH . '/views/profile/favorites.php';
    }

    public function store(string $slug): void
    {
        $user = $this->requireAuth();

        if (!$this->verifyCsrf()) {
            $this->redirect('/oyunlar/' . $slug);
        }

        $favoriteModel = new Favorite();
        $favoriteModel->add((int) $user['id'], $slug);

        $this->redirect('/oyunlar/' . $slug);
    }

    public function destroy(string $slug): void
    {
        $user = $this->requireAuth();

        if (!$this->verifyCsrf()) {
            $this->redirect('/favorilerim');
        }

        $favoriteModel = new Favorite();
        $favoriteModel->remove((int) $user['id'], $slug);

        $this->redirect('/favorilerim');
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