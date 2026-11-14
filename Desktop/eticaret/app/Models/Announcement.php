<?php
// app/Models/Announcement.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Duyuru şeridi (announcements). */
class Announcement extends BaseModel
{
    protected string $table = 'announcements';
    protected string $primaryKey = 'id';
    protected array $fillable = ['message', 'link_url', 'sort_order', 'starts_at', 'ends_at', 'is_active'];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'sort_order', 'created_at'];

    public function activeList(): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM announcements
              WHERE is_active = 1
                AND (starts_at IS NULL OR starts_at <= NOW())
                AND (ends_at IS NULL OR ends_at >= NOW())
              ORDER BY sort_order ASC, id ASC'
        );
    }

    public function adminList(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $db = Database::instance();
        $total = (int) $db->fetchValue('SELECT COUNT(*) FROM announcements');
        $items = $db->fetchAll(
            'SELECT * FROM announcements ORDER BY sort_order ASC, id ASC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}