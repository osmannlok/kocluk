<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

class Favorite
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.id, g.title, g.slug, g.cover_image, g.age_group, c.name AS category_name, f.created_at
             FROM favorites f
             INNER JOIN games g ON g.id = f.game_id
             INNER JOIN categories c ON c.id = g.category_id
             WHERE f.user_id = :user_id AND g.is_active = 1
             ORDER BY f.created_at DESC'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function add(int $userId, string $gameSlug): bool
    {
        $gameId = $this->resolveGameId($gameSlug);

        if ($gameId === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO favorites (user_id, game_id, created_at)
             VALUES (:user_id, :game_id, NOW())'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'game_id' => $gameId,
        ]);
    }

    public function remove(int $userId, string $gameSlug): bool
    {
        $gameId = $this->resolveGameId($gameSlug);

        if ($gameId === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM favorites
             WHERE user_id = :user_id AND game_id = :game_id'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'game_id' => $gameId,
        ]);
    }

    public function isFavorite(int $userId, int $gameId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT user_id
             FROM favorites
             WHERE user_id = :user_id AND game_id = :game_id
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'game_id' => $gameId,
        ]);

        return $stmt->fetch() !== false;
    }

    private function resolveGameId(string $gameSlug): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM games
             WHERE slug = :slug
             LIMIT 1'
        );
        $stmt->execute(['slug' => $gameSlug]);

        $row = $stmt->fetch();

        return $row === false ? null : (int) $row['id'];
    }
}