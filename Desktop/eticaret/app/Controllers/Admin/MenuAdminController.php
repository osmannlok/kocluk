<?php
// app/Controllers/Admin/MenuAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Region;

/**
 * Menü yönetimi (menu.manage).
 * Güvenlik: her metot require() ile korunur; öğeler her işlemde menü kimliğiyle eşleştirilir (IDOR);
 * link_type beyaz listedir, hedefin varlığı doğrulanır, url türü yalnızca "/yol" veya https:// kabul eder;
 * sıralamada döngü ve 3 seviye sınırı sunucuda zorlanır; her işlem audit_logs'a yazılır.
 * Not: menu_items tablosunda deleted_at yoktur; öğe silme kalıcıdır (alt öğeler FK CASCADE ile gider),
 * silinen kaydın özeti denetim kaydında saklanır.
 */
final class MenuAdminController extends AdminBaseController
{
    private const MAX_DEPTH = 3;

    /** link_type => Türkçe etiket (menu_items.link_type ENUM beyaz listesi). */
    private const LINK_TYPES = [
        'url' => 'Özel bağlantı',
        'category' => 'Kategori',
        'page' => 'Sayfa',
        'region' => 'Yöre',
        'products' => 'Tüm ürünler',
    ];

    private function menus(): Menu
    {
        return new Menu();
    }

    private function items(): MenuItem
    {
        return new MenuItem();
    }

    public function index(Request $request): Response
    {
        $this->require('menu.manage');

        return $this->view('admin.menus.index', [
            'title' => 'Menüler',
            'menus' => $this->menus()->allWithItemCounts(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $this->require('menu.manage');
        $menu = $this->findMenu($id);

        return $this->view('admin.menus.edit', [
            'title' => 'Menü: ' . (string) $menu['name'],
            'menu' => $menu,
            'tree' => $this->items()->treeByMenu($id, false),
            'linkTypes' => self::LINK_TYPES,
            'maxDepth' => self::MAX_DEPTH,
            'categories' => (new Category())->allFlat(),
            'pages' => (new Page())->all(['is_active' => 1], 'id ASC'),
            'regions' => (new Region())->activeAll(),
            'reorderUrl' => url('/yonetim/menuler/' . $id . '/sirala'),
            'pageScripts' => ['js/admin-menu.js'],
        ]);
    }

    public function storeItem(Request $request, int $id): Response
    {
        $this->require('menu.manage');
        $this->findMenu($id);

        $data = $this->collect($request);

        // Üst öğe: aynı menüde olmalı ve yeni öğe 3. seviyeyi aşmamalı.
        $parentId = $this->parseParentId($request);
        if ($parentId !== null) {
            $depths = $this->depthMap($id);
            if (!isset($depths[$parentId])) {
                throw new ValidationException(['parent_id' => 'Üst öğe bulunamadı.'], $this->oldValues($request));
            }
            if ($depths[$parentId] + 1 > self::MAX_DEPTH) {
                throw new ValidationException(
                    ['parent_id' => 'Menü en çok ' . self::MAX_DEPTH . ' seviye olabilir.'],
                    $this->oldValues($request)
                );
            }
        }

        $data['menu_id'] = $id;
        $data['parent_id'] = $parentId;
        $data['sort_order'] = $this->nextSortOrder($id, $parentId);

        $itemId = $this->items()->create($data);

        $this->audit('menu.item_create', 'menu_item', $itemId, null, $this->snapshot($data + ['menu_id' => $id]));
        $this->flash('success', 'Menü öğesi eklendi.');

        return $this->redirect(url('/yonetim/menuler/' . $id));
    }

    public function updateItem(Request $request, int $id, int $itemId): Response
    {
        $this->require('menu.manage');
        $this->findMenu($id);
        $item = $this->findItem($id, $itemId);

        // Üst öğe ve sıra burada değişmez; yalnızca reorder() ile değişir.
        $data = $this->collect($request);
        $this->items()->update($itemId, $data);

        $this->audit('menu.item_update', 'menu_item', $itemId, $this->snapshot($item), $this->snapshot($data + ['menu_id' => $id]));
        $this->flash('success', 'Menü öğesi güncellendi.');

        return $this->redirect(url('/yonetim/menuler/' . $id));
    }

    public function toggleItem(Request $request, int $id, int $itemId): Response
    {
        $this->require('menu.manage');
        $this->findMenu($id);
        $item = $this->findItem($id, $itemId);

        $new = ((int) $item['is_active']) === 1 ? 0 : 1;
        $this->items()->update($itemId, ['is_active' => $new]);

        $this->audit('menu.item_toggle', 'menu_item', $itemId, ['is_active' => (int) $item['is_active']], ['is_active' => $new]);
        $this->flash('success', $new === 1 ? 'Menü öğesi etkinleştirildi.' : 'Menü öğesi gizlendi.');

        return $this->redirect(url('/yonetim/menuler/' . $id));
    }

    public function destroyItem(Request $request, int $id, int $itemId): Response
    {
        $this->require('menu.manage');
        $this->findMenu($id);
        $item = $this->findItem($id, $itemId);

        $childIds = $this->items()->descendantIds($itemId);
        $this->items()->forceDelete($itemId);

        $this->audit(
            'menu.item_delete',
            'menu_item',
            $itemId,
            $this->snapshot($item) + ['deleted_descendants' => $childIds],
            null
        );
        $this->flash('success', 'Menü öğesi silindi' . ($childIds !== [] ? ' (alt öğeleriyle birlikte).' : '.'));

        return $this->redirect(url('/yonetim/menuler/' . $id));
    }

    /**
     * POST JSON: {"items":[{"id":5,"parent_id":null},{"id":7,"parent_id":5}]}
     * Liste, düz ve görsel sırayla verilir; sıra her üst öğe için 1'den yeniden numaralanır.
     * Yanıt: {"ok":true,"data":{"count":2}}
     */
    public function reorder(Request $request, int $id): Response
    {
        $this->require('menu.manage');
        $this->findMenu($id);

        $payload = $request->json();
        $rawItems = $payload['items'] ?? null;
        if (!is_array($rawItems) || $rawItems === []) {
            return $this->fail('validation', 'Sıralama listesi boş.', 422, ['items' => 'Sıralama listesi boş.']);
        }

        $parents = [];
        $order = [];
        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                return $this->invalidOrder();
            }
            $itemId = $this->toPositiveInt($row['id'] ?? null);
            if ($itemId === null || isset($parents[$itemId])) {
                return $this->invalidOrder();
            }
            $parentRaw = $row['parent_id'] ?? null;
            $parentId = null;
            if ($parentRaw !== null && $parentRaw !== '') {
                $parentId = $this->toPositiveInt($parentRaw);
                if ($parentId === null) {
                    return $this->invalidOrder();
                }
            }
            $parents[$itemId] = $parentId;
            $order[] = $itemId;
        }

        // Bütünlük: liste bu menünün tüm öğelerini birebir içermeli (başka menünün kaydına dokunulamaz).
        $existing = $this->depthMap($id);
        if (count($parents) !== count($existing)) {
            return $this->fail('validation', 'Liste güncel değil; sayfayı yenileyin.', 422, ['items' => 'Liste güncel değil.']);
        }
        foreach (array_keys($parents) as $itemId) {
            if (!isset($existing[$itemId])) {
                return $this->fail('validation', 'Liste güncel değil; sayfayı yenileyin.', 422, ['items' => 'Liste güncel değil.']);
            }
        }

        // Döngü engeli, geçerli üst öğe ve en çok 3 seviye.
        foreach ($parents as $itemId => $parentId) {
            if ($parentId !== null && !isset($parents[$parentId])) {
                return $this->fail('validation', 'Üst öğe geçersiz.', 422, ['items' => 'Üst öğe geçersiz.']);
            }
            $depth = 1;
            $seen = [$itemId => true];
            $cursor = $parentId;
            while ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    return $this->fail('validation', 'Bir öğe kendi alt öğesinin altına taşınamaz.', 422, ['items' => 'Döngü tespit edildi.']);
                }
                $seen[$cursor] = true;
                $depth++;
                if ($depth > self::MAX_DEPTH) {
                    return $this->fail(
                        'validation',
                        'Menü en çok ' . self::MAX_DEPTH . ' seviye olabilir.',
                        422,
                        ['items' => 'Seviye sınırı aşıldı.']
                    );
                }
                $cursor = $parents[$cursor];
            }
        }

        $counters = [];
        $updates = [];
        foreach ($order as $itemId) {
            $parentId = $parents[$itemId];
            $key = $parentId === null ? 0 : $parentId;
            $counters[$key] = ($counters[$key] ?? 0) + 1;
            $updates[$itemId] = ['parent_id' => $parentId, 'sort_order' => $counters[$key]];
        }

        $items = $this->items();
        Database::instance()->transaction(static function () use ($items, $updates): void {
            foreach ($updates as $itemId => $fields) {
                $items->update($itemId, $fields);
            }
        });

        $this->audit('menu.reorder', 'menu', $id, null, ['items' => $updates]);

        return $this->ok(['count' => count($updates)]);
    }

    private function invalidOrder(): Response
    {
        return $this->fail('validation', 'Sıralama listesi geçersiz.', 422, ['items' => 'Sıralama listesi geçersiz.']);
    }

    /** @return array<string,mixed> */
    private function findMenu(int $id): array
    {
        $menu = $this->menus()->find($id);
        if ($menu === null) {
            throw new HttpException(404, 'Menü bulunamadı.');
        }

        return $menu;
    }

    /** Öğe, yalnızca verilen menüye aitse döner (IDOR). @return array<string,mixed> */
    private function findItem(int $menuId, int $itemId): array
    {
        $item = $this->items()->find($itemId);
        if ($item === null || (int) $item['menu_id'] !== $menuId) {
            throw new HttpException(404, 'Menü öğesi bulunamadı.');
        }

        return $item;
    }

    /** Menüdeki tüm öğeler (pasifler dahil): id => derinlik (kök = 1). @return array<int,int> */
    private function depthMap(int $menuId): array
    {
        $map = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$map): void {
            foreach ($nodes as $node) {
                $map[(int) $node['id']] = $depth;
                if (!empty($node['children']) && is_array($node['children'])) {
                    $walk($node['children'], $depth + 1);
                }
            }
        };
        $walk($this->items()->treeByMenu($menuId, false), 1);

        return $map;
    }

    private function nextSortOrder(int $menuId, ?int $parentId): int
    {
        $where = ['menu_id' => $menuId, 'parent_id' => $parentId];
        $max = 0;
        foreach ($this->items()->all($where) as $row) {
            $max = max($max, (int) $row['sort_order']);
        }

        return $max + 1;
    }

    private function parseParentId(Request $request): ?int
    {
        $raw = $request->post('parent_id', '');
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }
        $id = $this->toPositiveInt($raw);
        if ($id === null) {
            throw new ValidationException(['parent_id' => 'Üst öğe geçersiz.'], $this->oldValues($request));
        }

        return $id;
    }

    private function toPositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 1 ? $value : null;
        }
        if (is_string($value) && preg_match('/^\d{1,18}$/', $value) === 1 && (int) $value >= 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Girdiyi doğrular; hata varsa ValidationException fırlatır.
     * @return array<string,mixed>
     */
    private function collect(Request $request): array
    {
        $errors = [];

        $label = $this->text($request, 'label', 100, 'Etiket', $errors, true);
        $icon = $this->text($request, 'icon', 60, 'Simge', $errors);
        $badge = $this->text($request, 'badge_text', 20, 'Rozet metni', $errors);

        if ($icon !== '' && !isset($errors['icon']) && preg_match('/^[a-z0-9_-]{1,60}$/', $icon) !== 1) {
            $errors['icon'] = 'Simge adı yalnızca küçük harf, rakam, tire ve alt çizgi içerebilir.';
        }

        $type = (string) $request->post('link_type', '');
        if (!isset(self::LINK_TYPES[$type])) {
            $errors['link_type'] = 'Bağlantı türü geçersiz.';
            $type = '';
        }

        $value = $this->text($request, 'link_value', 255, 'Hedef', $errors);
        $linkValue = null;
        if ($type !== '' && !isset($errors['link_value'])) {
            switch ($type) {
                case 'url':
                    if ($value === '') {
                        $errors['link_value'] = 'Bağlantı adresi zorunludur.';
                    } elseif (!$this->isSafeLink($value)) {
                        $errors['link_value'] = 'Bağlantı yalnızca "/" ile başlayan site içi yol veya "https://" ile başlayan adres olabilir.';
                    } else {
                        $linkValue = $value;
                    }
                    break;
                case 'category':
                    if ($value === '' || (new Category())->findBySlug($value) === null) {
                        $errors['link_value'] = 'Seçilen kategori bulunamadı veya pasif.';
                    } else {
                        $linkValue = $value;
                    }
                    break;
                case 'page':
                    if ($value === '' || (new Page())->findActiveBySlug($value) === null) {
                        $errors['link_value'] = 'Seçilen sayfa bulunamadı veya pasif.';
                    } else {
                        $linkValue = $value;
                    }
                    break;
                case 'region':
                    if ($value === '' || (new Region())->findBySlug($value) === null) {
                        $errors['link_value'] = 'Seçilen yöre bulunamadı veya pasif.';
                    } else {
                        $linkValue = $value;
                    }
                    break;
                case 'products':
                    // Hedef gerekmez; /urunler sabittir.
                    $linkValue = null;
                    break;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors, $this->oldValues($request));
        }

        return [
            'label' => $label,
            'link_type' => $type,
            'link_value' => $linkValue,
            'icon' => $icon !== '' ? $icon : null,
            'badge_text' => $badge !== '' ? $badge : null,
            'is_mega' => $request->post('is_mega') === '1' ? 1 : 0,
            'open_new_tab' => $request->post('open_new_tab') === '1' ? 1 : 0,
        ];
    }

    /** Açık yönlendirme/XSS koruması: yalnızca "/yol" (// değil) veya https://. */
    private function isSafeLink(string $url): bool
    {
        // Boşluk, kontrol karakteri ve ters bölü ("/\evil.com" hilesi) reddedilir.
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return false;
        }
        if ($url[0] === '/') {
            return !str_starts_with($url, '//');
        }
        if (stripos($url, 'https://') === 0) {
            $parts = parse_url($url);

            return is_array($parts)
                && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
                && !empty($parts['host'])
                && !isset($parts['user'], $parts['pass']);
        }

        return false;
    }

    /** @param array<string,string> $errors */
    private function text(Request $request, string $key, int $max, string $label, array &$errors, bool $required = false): string
    {
        $value = $request->post($key, '');
        $value = is_string($value) ? trim($value) : '';

        if (!mb_check_encoding($value, 'UTF-8')) {
            $errors[$key] = $label . ' geçersiz karakter içeriyor.';
            return '';
        }

        $value = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        if ($required && $value === '') {
            $errors[$key] = $label . ' zorunludur.';
        } elseif (mb_strlen($value) > $max) {
            $errors[$key] = $label . ' en çok ' . $max . ' karakter olabilir.';
        }

        return $value;
    }

    /**
     * Hata durumunda forma geri dönecek değerler.
     * @return array<string,string>
     */
    private function oldValues(Request $request): array
    {
        $out = [];
        foreach (['label', 'link_type', 'link_value', 'icon', 'badge_text', 'parent_id'] as $key) {
            $value = $request->post($key, '');
            $out[$key] = is_string($value) ? $value : '';
        }
        $out['is_mega'] = $request->post('is_mega') === '1' ? '1' : '0';
        $out['open_new_tab'] = $request->post('open_new_tab') === '1' ? '1' : '0';

        return $out;
    }

    /**
     * Denetim kaydı özeti.
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function snapshot(array $row): array
    {
        $out = [];
        $keys = [
            'menu_id', 'parent_id', 'label', 'link_type', 'link_value', 'icon', 'badge_text',
            'is_mega', 'open_new_tab', 'sort_order', 'is_active',
        ];
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }
}