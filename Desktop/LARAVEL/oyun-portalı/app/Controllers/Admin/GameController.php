<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Models\Game;

class GameController extends AdminController
{
    public function index(): void
    {
        $gameModel = new Game();

        $page = isset($_GET['sayfa']) ? max(1, (int) $_GET['sayfa']) : 1;
        $perPage = (require BASE_PATH . '/config/app.php')['pagination']['per_page'];

        $games = $gameModel->getPaginated($page, $perPage);
        $totalGames = $gameModel->countAll();
        $totalPages = (int) ceil($totalGames / $perPage);
        $categories = $gameModel->getCategories();

        $title = 'Oyun Yönetimi - Admin Panel';
        $description = 'Oyunları listele, düzenle ve yönet.';

        require BASE_PATH . '/views/admin/games/index.php';
    }
}