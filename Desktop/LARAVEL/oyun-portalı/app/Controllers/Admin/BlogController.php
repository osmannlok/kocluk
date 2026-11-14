<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Models\BlogPost;

class BlogController extends AdminController
{
    public function index(): void
    {
        $blogModel = new BlogPost();

        $page = isset($_GET['sayfa']) ? max(1, (int) $_GET['sayfa']) : 1;
        $perPage = (require BASE_PATH . '/config/app.php')['pagination']['per_page'];

        $posts = $blogModel->getPaginated($page, $perPage);
        $totalPosts = $blogModel->countAll();
        $totalPages = (int) ceil($totalPosts / $perPage);

        $title = 'Blog Yönetimi - Admin Panel';
        $description = 'Blog yazılarını listele, düzenle ve yönet.';

        require BASE_PATH . '/views/admin/blog/index.php';
    }
}