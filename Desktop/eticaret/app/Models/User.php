<?php
// app/Models/User.php

declare(strict_types=1);

namespace App\Models;

final class User extends BaseModel
{
    protected string $table = 'users';
    protected bool $softDeletes = true;

    protected array $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password_hash',
        'email_verified_at',
        'status',
        'kvkk_consent_at',
        'marketing_consent',
        'last_login_at',
        'last_login_ip',
        'failed_login_count',
        'locked_until',
    ];

    protected array $extraColumns = ['created_at', 'updated_at'];

    private const STATUSES = ['active', 'suspended', 'pending'];

    /** Parola özeti ve ikili IP içermeyen güvenli kolon listesi (görünüm/JSON için). */
    private const SAFE_COLUMNS = 'u.id, u.role_id, u.first_name, u.last_name, u.email, u.phone, '
        . 'u.email_verified_at, u.status, u.kvkk_consent_at, u.marketing_consent, u.last_login_at, '
        . 'u.failed_login_count, u.locked_until, u.created_at, u.updated_at';

    public function findByEmail(string $email): ?array
    {
        $email = trim($email);
        if ($email === '' || mb_strlen($email) > 190) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT * FROM `users` WHERE `email` = :email AND `deleted_at` IS NULL LIMIT 1',
            [':email' => $email]
        );
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        // Silinmiş kayıtlar da sayılır: UNIQUE indeks onlar için de geçerlidir.
        $sql = 'SELECT COUNT(*) FROM `users` WHERE `email` = :email';
        $params = [':email' => trim($email)];
        if ($exceptId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $params[':except_id'] = $exceptId;
        }

        return (int) $this->db->fetchValue($sql, $params) > 0;
    }

    public function recordLogin(int $id, string $ipBin): void
    {
        $hex = $this->ipHex($ipBin);

        $this->db->execute(
            'UPDATE `users` SET `last_login_at` = NOW(), `last_login_ip` = UNHEX(:ip), '
            . '`failed_login_count` = 0, `locked_until` = NULL WHERE `id` = :id AND `deleted_at` IS NULL',
            [':ip' => $hex, ':id' => $id]
        );
    }

    public function incrementFailed(int $id): int
    {
        // TINYINT UNSIGNED taşmasını önlemek için üst sınır 255.
        $this->db->execute(
            'UPDATE `users` SET `failed_login_count` = LEAST(`failed_login_count` + 1, 255) WHERE `id` = :id',
            [':id' => $id]
        );

        return (int) $this->db->fetchValue(
            'SELECT `failed_login_count` FROM `users` WHERE `id` = :id',
            [':id' => $id]
        );
    }

    public function resetFailed(int $id): void
    {
        $this->db->execute(
            'UPDATE `users` SET `failed_login_count` = 0, `locked_until` = NULL WHERE `id` = :id',
            [':id' => $id]
        );
    }

    public function lockUntil(int $id, string $datetime): void
    {
        $this->assertDateTime($datetime);

        $this->db->execute(
            'UPDATE `users` SET `locked_until` = :until WHERE `id` = :id',
            [':until' => $datetime, ':id' => $id]
        );
    }

    public function withRole(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT ' . self::SAFE_COLUMNS . ', r.`name` AS role_name, r.`label` AS role_label '
            . 'FROM `users` u INNER JOIN `roles` r ON r.`id` = u.`role_id` '
            . 'WHERE u.`id` = :id AND u.`deleted_at` IS NULL LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Filtreler: q (ad/e-posta/telefon), role_id, status.
     *
     * @return array{items: list<array>, total: int, page: int, per_page: int, pages: int}
     */
    public function searchAdmin(array $filters, int $page, int $perPage): array
    {
        $clauses = ['u.`deleted_at` IS NULL'];
        $params = [];

        $q = isset($filters['q']) && is_string($filters['q']) ? trim(mb_substr($filters['q'], 0, 100)) : '';
        if ($q !== '') {
            $like = '%' . self::escapeLike($q) . '%';
            $clauses[] = '(' . implode(' OR ', [
                $this->likeSql('u.`first_name`', ':q_first'),
                $this->likeSql('u.`last_name`', ':q_last'),
                $this->likeSql('u.`email`', ':q_email'),
                $this->likeSql('u.`phone`', ':q_phone'),
                $this->likeSql("CONCAT(u.`first_name`, ' ', u.`last_name`)", ':q_full'),
            ]) . ')';
            foreach (['q_first', 'q_last', 'q_email', 'q_phone', 'q_full'] as $name) {
                $params[':' . $name] = $like;
            }
        }

        $roleId = (int) ($filters['role_id'] ?? 0);
        if ($roleId > 0) {
            $clauses[] = 'u.`role_id` = :role_id';
            $params[':role_id'] = $roleId;
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && in_array($status, self::STATUSES, true)) {
            $clauses[] = 'u.`status` = :status';
            $params[':status'] = $status;
        }

        return $this->paginateRaw(
            self::SAFE_COLUMNS . ', r.`name` AS role_name, r.`label` AS role_label',
            '`users` u INNER JOIN `roles` r ON r.`id` = u.`role_id`',
            implode(' AND ', $clauses),
            $params,
            'u.`id` DESC',
            $page,
            $perPage
        );
    }
}