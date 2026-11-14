<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;

class PageController
{
    public function show(string $slug): void
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT title, slug, content, updated_at
             FROM site_pages
             WHERE slug = :slug
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $page = $stmt->fetch();

        if ($page === false) {
            http_response_code(404);
            require BASE_PATH . '/views/errors/404.php';
            return;
        }

        $title = $page['title'] . ' - Eğitim Oyun Portalı';
        $description = mb_substr(strip_tags($page['content']), 0, 155);

        require BASE_PATH . '/views/pages/show.php';
    }
}