<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

class Game
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getFeatured(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.id, g.title, g.slug, g.description, g.age_group, g.cover_image, g.category_id, g.is_premium, c.name AS category_name
             FROM games g
             INNER JOIN categories c ON c.id = g.category_id
             WHERE g.is_active = 1
             ORDER BY g.is_featured DESC, g.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getCategories(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slug
             FROM categories
             ORDER BY name ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getPaginated(int $page, int $perPage, ?string $categorySlug = null): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT g.id, g.title, g.slug, g.description, g.age_group, g.cover_image, g.category_id, g.is_premium, c.name AS category_name, c.slug AS category_slug
                FROM games g
                INNER JOIN categories c ON c.id = g.category_id
                WHERE g.is_active = 1';

        $params = [];

        if ($categorySlug !== null && $categorySlug !== '') {
            $sql .= ' AND c.slug = :category_slug';
            $params['category_slug'] = $categorySlug;
        }

        $sql .= ' ORDER BY g.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(?string $categorySlug = null): int
    {
        $sql = 'SELECT COUNT(g.id) AS total
                FROM games g
                INNER JOIN categories c ON c.id = g.category_id
                WHERE g.is_active = 1';

        $params = [];

        if ($categorySlug !== null && $categorySlug !== '') {
            $sql .= ' AND c.slug = :category_slug';
            $params['category_slug'] = $categorySlug;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch();

        return $row === false ? 0 : (int) $row['total'];
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT g.id, g.title, g.slug, g.description, g.age_group, g.target_skill, g.cover_image, g.category_id, g.is_premium, c.name AS category_name, c.slug AS category_slug
             FROM games g
             INNER JOIN categories c ON c.id = g.category_id
             WHERE g.slug = :slug AND g.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);

        $game = $stmt->fetch();

        return $game === false ? null : $game;
    }

    public function getRelated(int $gameId, int $categoryId, int $limit = 4): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, slug, cover_image
             FROM games
             WHERE category_id = :category_id AND id != :game_id AND is_active = 1
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue('game_id', $gameId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}