<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password, role_id, created_at
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();

        return $user === false ? null : $user;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, role_id, created_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $user = $stmt->fetch();

        return $user === false ? null : $user;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        return $stmt->fetch() !== false;
    }

    public function create(string $name, string $email, string $passwordHash): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, role_id, created_at)
             VALUES (:name, :email, :password, :role_id, NOW())'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
            'role_id' => 2,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET password = :password
             WHERE id = :id'
        );

        return $stmt->execute([
            'password' => $passwordHash,
            'id' => $id,
        ]);
    }
}