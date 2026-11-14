<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Models\Game;

class CategoryController extends AdminController
{
    public function index(): void
    {
        $gameModel = new Game();
        $categories = $gameModel->getCategories();

        $title = 'Kategori Yönetimi - Admin Panel';
        $description = 'Kategorileri listele, düzenle ve yönet.';

        require BASE_PATH . '/views/admin/categories/index.php';
    }
}