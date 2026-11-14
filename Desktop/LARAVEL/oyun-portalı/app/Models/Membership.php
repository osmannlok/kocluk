<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

class Membership
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getActiveByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.status, m.starts_at, m.ends_at, p.name AS plan_name, p.slug AS plan_slug
             FROM memberships m
             INNER JOIN membership_plans p ON p.id = m.plan_id
             WHERE m.user_id = :user_id AND m.status = "active" AND m.ends_at >= NOW()
             ORDER BY m.ends_at DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);

        $membership = $stmt->fetch();

        return $membership === false ? null : $membership;
    }

    public function isActive(int $userId): bool
    {
        return $this->getActiveByUser($userId) !== null;
    }

    public function getPlans(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slug, description, price_cents, duration_days
             FROM membership_plans
             WHERE is_active = 1
             ORDER BY price_cents ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findPlanBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slug, description, price_cents, duration_days
             FROM membership_plans
             WHERE slug = :slug AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);

        $plan = $stmt->fetch();

        return $plan === false ? null : $plan;
    }

    public function create(int $userId, int $planId, int $durationDays): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO memberships (user_id, plan_id, status, starts_at, ends_at, created_at)
             VALUES (:user_id, :plan_id, "active", NOW(), DATE_ADD(NOW(), INTERVAL :duration_days DAY), NOW())'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('plan_id', $planId, PDO::PARAM_INT);
        $stmt->bindValue('duration_days', $durationDays, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    public function cancel(int $membershipId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE memberships
             SET status = "cancelled"
             WHERE id = :id AND user_id = :user_id'
        );

        return $stmt->execute([
            'id' => $membershipId,
            'user_id' => $userId,
        ]);
    }
}