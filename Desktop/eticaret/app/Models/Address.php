<?php
// app/Models/Address.php

declare(strict_types=1);

namespace App\Models;

final class Address extends BaseModel
{
    protected string $table = 'addresses';
    protected bool $softDeletes = true;

    protected array $fillable = [
        'user_id',
        'title',
        'full_name',
        'phone',
        'city',
        'district',
        'neighborhood',
        'address_line',
        'postal_code',
        'is_default',
        'invoice_type',
        'tc_identity_no',
        'company_name',
        'tax_office',
        'tax_number',
    ];

    protected array $extraColumns = ['created_at', 'updated_at'];

    /** @return list<array> */
    public function forUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `addresses` WHERE `user_id` = :user_id AND `deleted_at` IS NULL '
            . 'ORDER BY `is_default` DESC, `id` DESC',
            [':user_id' => $userId]
        );
    }

    /** IDOR koruması: kayıt her zaman user_id ile sınırlanır. */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM `addresses` WHERE `id` = :id AND `user_id` = :user_id AND `deleted_at` IS NULL LIMIT 1',
            [':id' => $id, ':user_id' => $userId]
        );
    }

    /** Adres kullanıcıya ait değilse hiçbir şey değişmez. */
    public function setDefault(int $id, int $userId): void
    {
        $this->db->transaction(function () use ($id, $userId): void {
            $row = $this->db->fetchOne(
                'SELECT `id` FROM `addresses` WHERE `id` = :id AND `user_id` = :user_id AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE',
                [':id' => $id, ':user_id' => $userId]
            );
            if ($row === null) {
                return;
            }

            $this->db->execute(
                'UPDATE `addresses` SET `is_default` = 0 WHERE `user_id` = :user_id AND `is_default` = 1 AND `id` <> :id',
                [':user_id' => $userId, ':id' => $id]
            );
            $this->db->execute(
                'UPDATE `addresses` SET `is_default` = 1 WHERE `id` = :id AND `user_id` = :user_id',
                [':id' => $id, ':user_id' => $userId]
            );
        });
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `addresses` WHERE `user_id` = :user_id AND `deleted_at` IS NULL',
            [':user_id' => $userId]
        );
    }
}