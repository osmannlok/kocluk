<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;

class SitemapController
{
    public function index(): void
    {
        $db = Database::getConnection();
        $appConfig = require BASE_PATH . '/config/app.php';
        $baseUrl = rtrim($appConfig['url'], '/');

        $urls = [];

        $urls[] = ['loc' => $baseUrl . '/', 'changefreq' => 'daily', 'priority' => '1.0'];
        $urls[] = ['loc' => $baseUrl . '/oyunlar', 'changefreq' => 'daily', 'priority' => '0.9'];
        $urls[] = ['loc' => $baseUrl . '/blog', 'changefreq' => 'daily', 'priority' => '0.7'];

        $gameStmt = $db->prepare(
            'SELECT slug, updated_at, created_at
             FROM games
             WHERE is_active = 1'
        );
        $gameStmt->execute();
        foreach ($gameStmt->fetchAll() as $game) {
            $urls[] = [
                'loc' => $baseUrl . '/oyunlar/' . $game['slug'],
                'lastmod' => $game['updated_at'] ?? $game['created_at'],
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        $postStmt = $db->prepare(
            'SELECT slug, updated_at, published_at
             FROM blog_posts
             WHERE is_published = 1'
        );
        $postStmt->execute();
        foreach ($postStmt->fetchAll() as $post) {
            $urls[] = [
                'loc' => $baseUrl . '/blog/' . $post['slug'],
                'lastmod' => $post['updated_at'] ?? $post['published_at'],
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];
        }

        $pageStmt = $db->prepare(
            'SELECT slug, updated_at, created_at
             FROM site_pages'
        );
        $pageStmt->execute();
        foreach ($pageStmt->fetchAll() as $page) {
            $urls[] = [
                'loc' => $baseUrl . '/sayfa/' . $page['slug'],
                'lastmod' => $page['updated_at'] ?? $page['created_at'],
                'changefreq' => 'yearly',
                'priority' => '0.3',
            ];
        }

        header('Content-Type: application/xml; charset=utf-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . "\n";
            if (!empty($url['lastmod'])) {
                echo '    <lastmod>' . htmlspecialchars(date('Y-m-d', strtotime($url['lastmod']))) . '</lastmod>' . "\n";
            }
            echo '    <changefreq>' . htmlspecialchars($url['changefreq']) . '</changefreq>' . "\n";
            echo '    <priority>' . htmlspecialchars($url['priority']) . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }
}