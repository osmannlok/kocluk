<?php
// app/Models/PasswordReset.php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class PasswordReset extends BaseModel
{
    protected string $table = 'password_resets';
    protected bool $softDeletes = false;
    protected array $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at', 'ip_address'];
    protected array $extraColumns = ['created_at'];

    /** Yalnızca SHA-256 özeti saklanır; ham token asla DB'ye yazılmaz. */
    public function issue(int $userId, string $tokenHash, string $expiresAt, ?string $ipBin): int
    {
        if (preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1) {
            throw new InvalidArgumentException('Token özeti 64 karakterlik küçük harf hex olmalıdır.');
        }
        $this->assertDateTime($expiresAt);
        $hex = $this->ipHex($ipBin);

        return $this->db->insert(
            'INSERT INTO `password_resets` (`user_id`, `token_hash`, `expires_at`, `ip_address`) '
            . 'VALUES (:user_id, :token_hash, :expires_at, UNHEX(:ip))',
            [
                ':user_id' => $userId,
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
                ':ip' => $hex,
            ]
        );
    }

    /** Kullanılmamış, süresi dolmamış ve kullanıcısı silinmemiş kaydı döndürür. */
    public function findValidByHash(string $tokenHash): ?array
    {
        $tokenHash = strtolower($tokenHash);
        if (preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT t.`id`, t.`user_id`, t.`token_hash`, t.`expires_at`, t.`used_at`, t.`created_at` '
            . 'FROM `password_resets` t INNER JOIN `users` u ON u.`id` = t.`user_id` AND u.`deleted_at` IS NULL '
            . 'WHERE t.`token_hash` = :token_hash AND t.`used_at` IS NULL AND t.`expires_at` > NOW() LIMIT 1',
            [':token_hash' => $tokenHash]
        );
    }

    public function markUsed(int $id): void
    {
        $this->db->execute(
            'UPDATE `password_resets` SET `used_at` = NOW() WHERE `id` = :id AND `used_at` IS NULL',
            [':id' => $id]
        );
    }

    public function invalidateAllForUser(int $userId): void
    {
        $this->db->execute(
            'UPDATE `password_resets` SET `used_at` = NOW() WHERE `user_id` = :user_id AND `used_at` IS NULL',
            [':user_id' => $userId]
        );
    }
}