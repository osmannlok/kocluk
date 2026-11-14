<?php
// app/Models/Role.php

declare(strict_types=1);

namespace App\Models;

final class Role extends BaseModel
{
    protected string $table = 'roles';
    protected bool $softDeletes = false;
    protected array $fillable = ['name', 'label', 'description'];
    protected array $extraColumns = ['created_at'];

    public function findByName(string $name): ?array
    {
        if (preg_match('/^[a-z0-9_]{1,50}$/', $name) !== 1) {
            return null;
        }

        return $this->db->fetchOne('SELECT * FROM `roles` WHERE `name` = :name LIMIT 1', [':name' => $name]);
    }

    /** @return list<string> */
    public function permissionCodes(int $roleId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.`code` FROM `role_permissions` rp '
            . 'INNER JOIN `permissions` p ON p.`id` = rp.`permission_id` '
            . 'WHERE rp.`role_id` = :role_id ORDER BY p.`code` ASC',
            [':role_id' => $roleId]
        );

        return array_map(static fn (array $row): string => (string) $row['code'], $rows);
    }

    /** Rolün yetkilerini verilen kimliklerle değiştirir (tek transaction). */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $ids = $this->positiveInts($permissionIds);

        $this->db->transaction(function () use ($roleId, $ids): void {
            $this->db->execute(
                'DELETE FROM `role_permissions` WHERE `role_id` = :role_id',
                [':role_id' => $roleId]
            );

            if ($ids === []) {
                return;
            }

            $holders = [];
            $params = [];
            foreach ($ids as $i => $permissionId) {
                $holders[] = '(:role_' . $i . ', :perm_' . $i . ')';
                $params[':role_' . $i] = $roleId;
                $params[':perm_' . $i] = $permissionId;
            }

            $this->db->execute(
                'INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ' . implode(', ', $holders),
                $params
            );
        });
    }

    /** @return list<array> */
    public function allWithUserCounts(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT r.`id`, r.`name`, r.`label`, r.`description`, r.`created_at`, '
            . '(SELECT COUNT(*) FROM `users` u WHERE u.`role_id` = r.`id` AND u.`deleted_at` IS NULL) AS user_count, '
            . '(SELECT COUNT(*) FROM `role_permissions` rp WHERE rp.`role_id` = r.`id`) AS permission_count '
            . 'FROM `roles` r ORDER BY r.`id` ASC'
        );

        foreach ($rows as &$row) {
            $row['user_count'] = (int) $row['user_count'];
            $row['permission_count'] = (int) $row['permission_count'];
        }
        unset($row);

        return $rows;
    }
}