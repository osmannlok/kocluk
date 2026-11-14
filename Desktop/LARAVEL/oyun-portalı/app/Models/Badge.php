<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

class Badge
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT b.id, b.name, b.description, b.icon, ub.earned_at
             FROM user_badges ub
             INNER JOIN badges b ON b.id = ub.badge_id
             WHERE ub.user_id = :user_id
             ORDER BY ub.earned_at DESC'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description, icon
             FROM badges
             ORDER BY id ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function hasBadge(int $userId, int $badgeId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT user_id
             FROM user_badges
             WHERE user_id = :user_id AND badge_id = :badge_id
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'badge_id' => $badgeId,
        ]);

        return $stmt->fetch() !== false;
    }

    public function award(int $userId, int $badgeId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at)
             VALUES (:user_id, :badge_id, NOW())'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'badge_id' => $badgeId,
        ]);
    }
}