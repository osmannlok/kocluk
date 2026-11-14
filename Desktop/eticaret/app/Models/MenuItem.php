<?php
// app/Models/MenuItem.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Menü öğeleri (menu_items): çok seviyeli ağaç. link_value: slug (category/page/region) veya URL. */
class MenuItem extends BaseModel
{
    protected string $table = 'menu_items';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'menu_id', 'parent_id', 'label', 'link_type', 'link_value', 'icon', 'badge_text',
        'is_mega', 'open_new_tab', 'sort_order', 'is_active',
    ];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'sort_order'];

    /** Satırlara 'url' ve 'children' eklenir; pasif ebeveynin çocukları ağaca girmez. */
    public function treeByMenu(int $menuId, bool $onlyActive = true): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT * FROM menu_items WHERE menu_id = :m' . ($onlyActive ? ' AND is_active = 1' : '')
            . ' ORDER BY sort_order ASC, id ASC',
            [':m' => $menuId]
        );
        $groups = [];
        foreach ($rows as $r) {
            $r['url'] = $this->resolveUrl((string) $r['link_type'], $r['link_value'] !== null ? (string) $r['link_value'] : null);
            $groups[$r['parent_id'] === null ? 0 : (int) $r['parent_id']][] = $r;
        }
        return $this->buildTree($groups, 0);
    }

    /** $idToOrder: [öğe id => sort_order]. */
    public function reorder(array $idToOrder): void
    {
        Database::instance()->transaction(function () use ($idToOrder): void {
            foreach ($idToOrder as $id => $order) {
                Database::instance()->execute(
                    'UPDATE menu_items SET sort_order = :o WHERE id = :id',
                    [':o' => (int) $order, ':id' => (int) $id]
                );
            }
        });
    }

    /** Döngüsel ebeveyn atamasını engellemek için tüm alt öğe kimlikleri (UNION döngüde sonlanır). */
    public function descendantIds(int $id): array
    {
        $rows = Database::instance()->fetchAll(
            'WITH RECURSIVE t AS (
                SELECT id FROM menu_items WHERE parent_id = :id
                UNION
                SELECT m.id FROM menu_items m INNER JOIN t ON m.parent_id = t.id
             ) SELECT id FROM t',
            [':id' => $id]
        );
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    private function buildTree(array $groups, int $parentKey): array
    {
        $out = [];
        foreach ($groups[$parentKey] ?? [] as $row) {
            $row['is_mega'] = (int) $row['is_mega'];
            $row['open_new_tab'] = (int) $row['open_new_tab'];
            $row['children'] = $this->buildTree($groups, (int) $row['id']);
            $out[] = $row;
        }
        return $out;
    }

    private function resolveUrl(string $type, ?string $value): string
    {
        $v = trim((string) $value);
        return match ($type) {
            'category' => $v !== '' ? '/kategori/' . rawurlencode($v) : '#',
            'page' => $v !== '' ? '/sayfa/' . rawurlencode($v) : '#',
            'region' => $v !== '' ? '/yore/' . rawurlencode($v) : '#',
            'products' => '/urunler',
            default => $this->safeUrl($v),
        };
    }

    /** Yalnızca aynı site göreli yolu veya http(s); javascript:/data: ve //host reddedilir. */
    private function safeUrl(string $v): string
    {
        if ($v === '' || str_contains($v, '\\')) {
            return '#';
        }
        if (preg_match('#^/(?!/)#', $v) === 1 || preg_match('#^https?://#i', $v) === 1) {
            return $v;
        }
        return '#';
    }
}