<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Game;
use App\Services\GamePlayable;

class GameController
{
    public function index(): void
    {
        $gameModel = new Game();

        $page = isset($_GET['sayfa']) ? max(1, (int) $_GET['sayfa']) : 1;
        $perPage = (require BASE_PATH . '/config/app.php')['pagination']['per_page'];

        $categorySlug = isset($_GET['kategori']) ? (string) $_GET['kategori'] : null;

        $games = $gameModel->getPaginated($page, $perPage, $categorySlug);
        $totalGames = $gameModel->countAll($categorySlug);
        $totalPages = (int) ceil($totalGames / $perPage);
        $categories = $gameModel->getCategories();

        $title = 'Oyunlar - Eğitim Oyun Portalı';
        $description = 'Matematik, kelime, mantık, hafıza ve dikkat oyunlarının tamamını keşfedin.';

        require BASE_PATH . '/views/games/index.php';
    }

    public function show(string $slug): void
    {
        $gameModel = new Game();
        $game = $gameModel->findBySlug($slug);

        if ($game === null) {
            http_response_code(404);
            require BASE_PATH . '/views/errors/404.php';
            return;
        }

        $relatedGames = $gameModel->getRelated((int) $game['id'], (int) $game['category_id'], 4);
        $playableView = GamePlayable::viewPath($slug);

        $title = $game['title'] . ' - Eğitim Oyun Portalı';
        $description = $game['description'];

        require BASE_PATH . '/views/games/detail.php';
    }
}