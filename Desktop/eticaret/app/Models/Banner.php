<?php
// app/Models/Banner.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Bannerlar (banners, soft delete). Tarih penceresi ve sıra ile yayınlanır. */
class Banner extends BaseModel
{
    private const POSITIONS = ['home_hero', 'home_secondary', 'category_top'];

    protected string $table = 'banners';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'title', 'subtitle', 'button_text', 'link_url', 'image_desktop', 'image_mobile',
        'alt_text', 'position', 'sort_order', 'starts_at', 'ends_at', 'is_active', 'created_by',
    ];
    protected bool $softDeletes = true;
    protected array $sortable = ['id', 'sort_order', 'position', 'created_at'];

    public function activeByPosition(string $position): array
    {
        if (!in_array($position, self::POSITIONS, true)) {
            return [];
        }
        return Database::instance()->fetchAll(
            'SELECT * FROM banners
              WHERE position = :p AND is_active = 1 AND deleted_at IS NULL
                AND (starts_at IS NULL OR starts_at <= NOW())
                AND (ends_at IS NULL OR ends_at >= NOW())
              ORDER BY sort_order ASC, id ASC',
            [':p' => $position]
        );
    }

    public function adminList(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $db = Database::instance();
        $total = (int) $db->fetchValue('SELECT COUNT(*) FROM banners WHERE deleted_at IS NULL');
        $items = $db->fetchAll(
            'SELECT * FROM banners WHERE deleted_at IS NULL
              ORDER BY position ASC, sort_order ASC, id ASC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}