<?php
// app/Models/Page.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** CMS sayfaları (pages, soft delete). content: HTMLPurifier'dan geçmiş HTML (servis sorumluluğu). */
class Page extends BaseModel
{
    protected string $table = 'pages';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'title', 'slug', 'system_key', 'content', 'is_active', 'show_in_footer',
        'meta_title', 'meta_description',
    ];
    protected bool $softDeletes = true;
    protected array $sortable = ['id', 'title', 'created_at'];

    public function findActiveBySlug(string $slug): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM pages WHERE slug = :s AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            [':s' => $slug]
        );
    }

    /** Sistem sayfası (kvkk, distance_sales...) pasif olsa da silinmemişse döner; yayın kararı çağırandadır. */
    public function findBySystemKey(string $key): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM pages WHERE system_key = :k AND deleted_at IS NULL LIMIT 1',
            [':k' => $key]
        );
    }

    public function footerPages(): array
    {
        return Database::instance()->fetchAll(
            'SELECT id, title, slug, system_key FROM pages
              WHERE is_active = 1 AND show_in_footer = 1 AND deleted_at IS NULL
              ORDER BY id ASC'
        );
    }

    /** UNIQUE slug silinmişleri de kapsar; bu yüzden deleted_at filtresi yok. */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM pages WHERE slug = :s';
        $p = [':s' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :x';
            $p[':x'] = $exceptId;
        }
        return (int) Database::instance()->fetchValue($sql, $p) > 0;
    }
}