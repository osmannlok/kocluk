<?php
// app/Models/Region.php

declare(strict_types=1);

namespace App\Models;

final class Region extends BaseModel
{
    protected string $table = 'regions';
    protected bool $softDeletes = false;
    protected array $fillable = ['name', 'slug', 'sort_order', 'is_active'];
    protected array $extraColumns = ['created_at'];

    /** @return list<array> */
    public function activeAll(): array
    {
        return $this->db->fetchAll(
            'SELECT `id`, `name`, `slug`, `sort_order` FROM `regions` WHERE `is_active` = 1 '
            . 'ORDER BY `sort_order` ASC, `name` ASC, `id` ASC'
        );
    }

    /** Yalnızca aktif yöre döner. */
    public function findBySlug(string $slug): ?array
    {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 120) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT * FROM `regions` WHERE `slug` = :slug AND `is_active` = 1 LIMIT 1',
            [':slug' => $slug]
        );
    }
}