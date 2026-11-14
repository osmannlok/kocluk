<?php
// app/Models/Coupon.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Kuponlar (coupons, soft delete). used_count yalnızca atomik metotlarla değişir. */
class Coupon extends BaseModel
{
    protected string $table = 'coupons';
    protected string $primaryKey = 'id';
    // used_count bilerek fillable dışı: mass assignment ile şişirilemez.
    protected array $fillable = [
        'code', 'description', 'discount_type', 'discount_value', 'max_discount_amount',
        'min_cart_total', 'usage_limit', 'usage_per_user', 'starts_at', 'ends_at',
        'is_active', 'created_by',
    ];
    protected bool $softDeletes = true;
    protected array $sortable = ['id', 'code', 'created_at', 'used_count', 'ends_at'];

    public function findByCode(string $code): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM coupons WHERE code = :c AND deleted_at IS NULL LIMIT 1',
            [':c' => trim($code)]
        );
    }

    /** Toplam kullanım limiti dolmadıysa tek atomik UPDATE ile artırır; yarış durumunda limit aşılmaz. */
    public function incrementUsedIfAvailable(int $id): bool
    {
        $n = Database::instance()->execute(
            'UPDATE coupons SET used_count = used_count + 1
              WHERE id = :id AND deleted_at IS NULL
                AND (usage_limit IS NULL OR used_count < usage_limit)',
            [':id' => $id]
        );
        return $n > 0;
    }

    public function decrementUsed(int $id): void
    {
        Database::instance()->execute(
            'UPDATE coupons SET used_count = IF(used_count > 0, used_count - 1, 0) WHERE id = :id',
            [':id' => $id]
        );
    }

    public function adminList(array $filters, int $page, int $perPage): array
    {
        $where = ['deleted_at IS NULL'];
        $p = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = 'code LIKE :q';
            $p[':q'] = '%' . $this->escapeLike($q) . '%';
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'is_active = :a';
            $p[':a'] = (int) (bool) $filters['is_active'];
        }
        $w = ' WHERE ' . implode(' AND ', $where);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $db = Database::instance();
        $total = (int) $db->fetchValue('SELECT COUNT(*) FROM coupons' . $w, $p);
        $items = $db->fetchAll(
            'SELECT * FROM coupons' . $w . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $p
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** UNIQUE code silinmişleri de kapsar; bu yüzden deleted_at filtresi yok. */
    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM coupons WHERE code = :c';
        $p = [':c' => trim($code)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :x';
            $p[':x'] = $exceptId;
        }
        return (int) Database::instance()->fetchValue($sql, $p) > 0;
    }

    private function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}