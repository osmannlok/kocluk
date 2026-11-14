<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogPost;

class BlogController
{
    public function index(): void
    {
        $blogModel = new BlogPost();

        $page = isset($_GET['sayfa']) ? max(1, (int) $_GET['sayfa']) : 1;
        $perPage = (require BASE_PATH . '/config/app.php')['pagination']['per_page'];

        $posts = $blogModel->getPaginated($page, $perPage);
        $totalPosts = $blogModel->countAll();
        $totalPages = (int) ceil($totalPosts / $perPage);

        $title = 'Blog - Eğitim Oyun Portalı';
        $description = 'Eğitim teknolojisi, oyunlaştırma ve ebeveyn rehberleri üzerine yazılar.';

        require BASE_PATH . '/views/blog/index.php';
    }

    public function show(string $slug): void
    {
        $blogModel = new BlogPost();
        $post = $blogModel->findBySlug($slug);

        if ($post === null) {
            http_response_code(404);
            require BASE_PATH . '/views/errors/404.php';
            return;
        }

        $relatedPosts = $blogModel->getRecent(3, (int) $post['id']);

        $title = $post['title'] . ' - Blog - Eğitim Oyun Portalı';
        $description = $post['excerpt'] ?? mb_substr(strip_tags($post['content']), 0, 155);

        require BASE_PATH . '/views/blog/detail.php';
    }
}