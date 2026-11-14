<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

class Score
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.id, s.score, s.created_at, g.title, g.slug, g.cover_image
             FROM scores s
             INNER JOIN games g ON g.id = s.game_id
             WHERE s.user_id = :user_id
             ORDER BY s.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getBestByGame(int $userId, int $gameId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, score, created_at
             FROM scores
             WHERE user_id = :user_id AND game_id = :game_id
             ORDER BY score DESC
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'game_id' => $gameId,
        ]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function record(int $userId, string $gameSlug, int $points): bool
    {
        $gameId = $this->resolveGameId($gameSlug);

        if ($gameId === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO scores (user_id, game_id, score, created_at)
             VALUES (:user_id, :game_id, :score, NOW())'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'game_id' => $gameId,
            'score' => $points,
        ]);
    }

    public function getTopScores(int $gameId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.score, s.created_at, u.name
             FROM scores s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.game_id = :game_id
             ORDER BY s.score DESC
             LIMIT :limit'
        );
        $stmt->bindValue('game_id', $gameId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
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