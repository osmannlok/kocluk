<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

class BlogPost
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getPaginated(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            'SELECT id, title, slug, excerpt, cover_image, published_at
             FROM blog_posts
             WHERE is_published = 1
             ORDER BY published_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(id) AS total
             FROM blog_posts
             WHERE is_published = 1'
        );
        $stmt->execute();

        $row = $stmt->fetch();

        return $row === false ? 0 : (int) $row['total'];
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, slug, excerpt, content, cover_image, published_at
             FROM blog_posts
             WHERE slug = :slug AND is_published = 1
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);

        $post = $stmt->fetch();

        return $post === false ? null : $post;
    }

    public function getRecent(int $limit = 3, ?int $excludeId = null): array
    {
        $sql = 'SELECT id, title, slug, cover_image
                FROM blog_posts
                WHERE is_published = 1';

        $params = [];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' ORDER BY published_at DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}