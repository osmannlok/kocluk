<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Game;

class HomeController
{
    public function index(): void
    {
        $gameModel = new Game();
        $featuredGames = $gameModel->getFeatured(6);
        $categories = $gameModel->getCategories();

        $title = 'Eğitim Oyun Portalı - Çocuklar İçin Eğlenceli Öğrenme Oyunları';
        $description = 'Matematik, kelime, mantık ve hafıza oyunlarıyla çocuklar için güvenli ve eğitici bir öğrenme deneyimi.';

        require BASE_PATH . '/views/home/index.php';
    }
}