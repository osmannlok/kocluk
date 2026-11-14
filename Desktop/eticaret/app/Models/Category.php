<?php
// app/Models/Category.php

declare(strict_types=1);

namespace App\Models;

final class Category extends BaseModel
{
    /** Ağaç gezinmelerinde döngü/derinlik koruması. */
    private const MAX_DEPTH = 10;

    protected string $table = 'categories';
    protected bool $softDeletes = true;

    protected array $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_path',
        'sort_order',
        'is_active',
        'meta_title',
        'meta_description',
    ];

    protected array $extraColumns = ['created_at', 'updated_at'];

    /**
     * Aktif kategoriler, alt kategoriler 'children' anahtarında iç içe.
     * Üst kategorisi pasif/silinmiş olan kategoriler ağaca girmez.
     *
     * @return list<array>
     */
    public function activeTree(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT `id`, `parent_id`, `name`, `slug`, `description`, `image_path`, `sort_order`, '
            . '`meta_title`, `meta_description` FROM `categories` '
            . 'WHERE `is_active` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC, `name` ASC, `id` ASC'
        );

        $byParent = [];
        foreach ($rows as $row) {
            $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
            $byParent[$key][] = $row;
        }

        return $this->attachChildren($byParent, 0);
    }

    /** @return list<array> Pasif olanlar dahil, silinmemiş tüm kategoriler. */
    public function allFlat(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `categories` WHERE `deleted_at` IS NULL '
            . 'ORDER BY COALESCE(`parent_id`, 0) ASC, `sort_order` ASC, `name` ASC, `id` ASC'
        );
    }

    /** Yalnızca aktif ve silinmemiş kategori döner. */
    public function findBySlug(string $slug): ?array
    {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 140) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT * FROM `categories` WHERE `slug` = :slug AND `is_active` = 1 AND `deleted_at` IS NULL LIMIT 1',
            [':slug' => $slug]
        );
    }

    /** @return list<array> Aktif doğrudan alt kategoriler. */
    public function childrenOf(int $parentId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `categories` WHERE `parent_id` = :parent_id AND `is_active` = 1 AND `deleted_at` IS NULL '
            . 'ORDER BY `sort_order` ASC, `name` ASC, `id` ASC',
            [':parent_id' => $parentId]
        );
    }

    /** @return list<array> Kökten verilen kategoriye kadar sıralı iz (id, parent_id, name, slug). */
    public function breadcrumb(int $categoryId): array
    {
        $trail = [];
        $seen = [];
        $currentId = $categoryId;

        for ($depth = 0; $depth < self::MAX_DEPTH && $currentId !== null; $depth++) {
            if (isset($seen[$currentId])) {
                break;
            }
            $seen[$currentId] = true;

            $row = $this->db->fetchOne(
                'SELECT `id`, `parent_id`, `name`, `slug` FROM `categories` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1',
                [':id' => $currentId]
            );
            if ($row === null) {
                break;
            }

            array_unshift($trail, $row);
            $currentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }

        return $trail;
    }

    /** Silinmiş kayıtlar da sayılır: slug UNIQUE indeksi onları da kapsar. */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `categories` WHERE `slug` = :slug';
        $params = [':slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND `id` <> :except_id';
            $params[':except_id'] = $exceptId;
        }

        return (int) $this->db->fetchValue($sql, $params) > 0;
    }

    /** @return list<int> Kategorinin tüm alt (torun dahil) kategori kimlikleri; kendisi hariç. */
    public function descendantIds(int $categoryId): array
    {
        $result = [];
        $seen = [$categoryId => true];
        $frontier = [$categoryId];

        for ($depth = 0; $depth < self::MAX_DEPTH && $frontier !== []; $depth++) {
            [$in, $params] = $this->inClause('`parent_id`', $frontier, 'parent');
            $rows = $this->db->fetchAll(
                'SELECT `id` FROM `categories` WHERE ' . $in . ' AND `deleted_at` IS NULL',
                $params
            );

            $frontier = [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                if (!isset($seen[$id])) {
                    $seen[$id] = true;
                    $result[] = $id;
                    $frontier[] = $id;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<int, list<array>> $byParent
     * @return list<array>
     */
    private function attachChildren(array $byParent, int $parentKey): array
    {
        $nodes = [];
        foreach ($byParent[$parentKey] ?? [] as $row) {
            $row['id'] = (int) $row['id'];
            $row['children'] = $this->attachChildren($byParent, $row['id']);
            $nodes[] = $row;
        }

        return $nodes;
    }
}