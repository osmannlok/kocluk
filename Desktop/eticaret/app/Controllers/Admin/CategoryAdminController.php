<?php
// app/Controllers/Admin/CategoryAdminController.php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Category;
use App\Models\Product;
use App\Services\ImageUploadService;
use PDOException;
use Throwable;

/**
 * Yönetim: kategori ağacı, oluşturma/düzenleme, silme, aktif/pasif ve sıralama.
 * SAPMA: planda kategori için ayrı servis yok; iş kuralları (slug, döngü, silme engeli) burada,
 * yalnızca model çağrıları ve transaction ile uygulanır. SQL yoktur.
 */
final class CategoryAdminController extends AdminBaseController
{
    /** Formdan kabul edilen alanlar (mass assignment koruması). */
    private const FIELDS = [
        'name', 'slug', 'parent_id', 'description', 'sort_order', 'is_active', 'meta_title', 'meta_description',
    ];

    /** Denetim kaydında karşılaştırılacak alanlar. */
    private const AUDIT_FIELDS = [
        'parent_id', 'name', 'slug', 'description', 'image_path', 'sort_order', 'is_active', 'meta_title', 'meta_description',
    ];

    /** Üst kategori zinciri en çok bu kadar seviye olabilir (activeTree derinlik sınırı 10 ile uyumlu). */
    private const MAX_DEPTH = 5;

    private Category $categories;
    private Product $products;
    private ImageUploadService $uploader;

    public function __construct(
        ?Category $categories = null,
        ?Product $products = null,
        ?ImageUploadService $uploader = null
    ) {
        if (method_exists(parent::class, '__construct')) {
            parent::__construct();
        }
        $this->categories = $categories ?? new Category();
        $this->products = $products ?? new Product();
        $this->uploader = $uploader ?? new ImageUploadService();
    }

    public function index(Request $request): Response
    {
        $this->require('category.manage');

        return $this->view('admin.categories.index', [
            'title'      => 'Kategoriler',
            'categories' => $this->flatten($this->categories->all()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->require('category.manage');

        $category = $this->blankCategory();
        $parent = $request->query('parent_id');
        if (is_string($parent) && ctype_digit($parent) && (int) $parent > 0) {
            $category['parent_id'] = (int) $parent;
        }

        return $this->renderForm($category, false);
    }

    public function store(Request $request): Response
    {
        $this->require('category.manage');

        return $this->persist($request, null);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->require('category.manage');

        return $this->renderForm($this->findOr404($id), true);
    }

    public function update(Request $request, int $id): Response
    {
        $this->require('category.manage');
        $this->findOr404($id);

        return $this->persist($request, $id);
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->require('category.manage');
        // Silme yetkisi: kategori için ayrı kod olmadığından yalnızca admin'de bulunan product.delete de istenir.
        $this->require('product.delete');

        $category = $this->findOr404($id);

        // Altında kategori veya ürün varsa silinmez (inaktifler dahil sayılır, soft delete'li olanlar hariç).
        if ($this->categories->count(['parent_id' => $id]) > 0) {
            $this->flash('error', 'Bu kategorinin alt kategorileri var; önce onları taşıyın veya silin.');

            return $this->redirect('/yonetim/kategoriler');
        }
        if ($this->products->count(['category_id' => $id]) > 0) {
            $this->flash('error', 'Bu kategoride ürün var; önce ürünleri başka kategoriye taşıyın.');

            return $this->redirect('/yonetim/kategoriler');
        }

        Database::instance()->transaction(function () use ($id, $category): void {
            $this->categories->softDelete($id);
            $this->audit(
                'category.delete',
                'category',
                $id,
                ['name' => $category['name'], 'slug' => $category['slug'], 'parent_id' => $category['parent_id']],
                ['deleted' => true]
            );
        });

        $this->flash('success', 'Kategori silindi.');

        return $this->redirect('/yonetim/kategoriler');
    }

    public function toggle(Request $request, int $id): Response
    {
        $this->require('category.manage');

        $category = $this->findOr404($id);
        $new = (int) $category['is_active'] === 1 ? 0 : 1;

        Database::instance()->transaction(function () use ($id, $category, $new): void {
            $this->categories->update($id, ['is_active' => $new]);
            $this->audit('category.toggle', 'category', $id, ['is_active' => (int) $category['is_active']], ['is_active' => $new]);
        });

        if ($request->wantsJson()) {
            return $this->ok(['id' => $id, 'is_active' => $new === 1]);
        }
        $this->flash('success', $new === 1 ? 'Kategori aktifleştirildi.' : 'Kategori pasifleştirildi.');

        return $this->redirect('/yonetim/kategoriler');
    }

    /**
     * Kardeşler arasında bir kategoriyi yukarı/aşağı taşır. JSON ({"id":int,"direction":"up|down"})
     * veya form gövdesi kabul eder; JSON istenmişse JSON, aksi halde yönlendirme döner.
     */
    public function reorder(Request $request): Response
    {
        $this->require('category.manage');

        $data = $request->json();
        if ($data === []) {
            $post = $request->post();
            $data = is_array($post) ? $post : [];
        }

        $rawId = $data['id'] ?? null;
        $id = is_int($rawId) ? $rawId : (is_string($rawId) && ctype_digit($rawId) ? (int) $rawId : 0);
        $direction = $data['direction'] ?? null;
        if ($id < 1 || ($direction !== 'up' && $direction !== 'down')) {
            return $this->fail('validation', 'Geçersiz sıralama isteği.', 422, ['direction' => 'Geçersiz sıralama isteği.']);
        }

        $category = $this->findOr404($id);
        $parentId = $category['parent_id'] === null ? null : (int) $category['parent_id'];

        $siblings = $this->categories->all(['parent_id' => $parentId]);
        usort($siblings, static fn (array $a, array $b): int => [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']]);

        $order = array_map(static fn (array $s): int => (int) $s['id'], $siblings);
        $pos = array_search($id, $order, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos !== false && isset($order[$swap])) {
            [$order[$pos], $order[$swap]] = [$order[$swap], $order[$pos]];

            $current = [];
            foreach ($siblings as $s) {
                $current[(int) $s['id']] = (int) $s['sort_order'];
            }

            // Tüm kardeşler 1..n olarak yeniden numaralanır (eşit sıra değerleri de düzelir).
            Database::instance()->transaction(function () use ($order, $current, $id, $direction): void {
                foreach ($order as $i => $cid) {
                    if (($current[$cid] ?? null) !== $i + 1) {
                        $this->categories->update($cid, ['sort_order' => $i + 1]);
                    }
                }
                $this->audit('category.reorder', 'category', $id, null, ['direction' => $direction, 'order' => $order]);
            });
        }

        if ($request->wantsJson()) {
            return $this->ok(['id' => $id, 'order' => $order]);
        }

        return $this->redirect('/yonetim/kategoriler');
    }

    // ------------------------------------------------------------------
    // Özel yardımcılar
    // ------------------------------------------------------------------

    private function persist(Request $request, ?int $id): Response
    {
        $existing = $id === null ? null : $this->findOr404($id);

        $post = $request->post();
        $post = is_array($post) ? $post : [];

        $errors = [];
        $row = $this->normalize($post, $id, $existing, $errors);

        $file = $request->file('image');
        $hasFile = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $remove = !$hasFile && filter_var($post['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($errors !== []) {
            throw new ValidationException($errors, $post);
        }

        // Görsel yalnızca diğer alanlar geçerliyse yüklenir; böylece boşuna dosya yazılmaz.
        $newPath = null;
        if ($hasFile) {
            try {
                $stored = $this->uploader->store($file, 'categories', ['thumb' => false, 'max_width' => 1200]);
            } catch (ValidationException $e) {
                $first = array_values($e->errors())[0] ?? 'Görsel yüklenemedi.';
                throw new ValidationException(['image' => (string) $first], $post);
            }
            $newPath = $stored['path'];
        }

        try {
            $savedId = Database::instance()->transaction(function () use ($id, $existing, $row, $newPath, $remove): int {
                if ($existing === null) {
                    $row['image_path'] = $newPath;
                    $cid = $this->categories->create($row);
                    $this->audit('category.create', 'category', $cid, null, $this->pick($row));

                    return $cid;
                }

                $row['image_path'] = $newPath ?? ($remove ? null : $existing['image_path']);
                $this->categories->update((int) $id, $row);
                [$oldDiff, $newDiff] = $this->diff($existing, $row);
                $this->audit('category.update', 'category', (int) $id, $oldDiff, $newDiff);

                return (int) $id;
            });
        } catch (PDOException $e) {
            $this->cleanup($newPath);
            // Eşzamanlı kayıtta UNIQUE(slug) ihlali doğrulama hatası olarak döner.
            if ((string) $e->getCode() === '23000' && stripos($e->getMessage(), 'Duplicate') !== false) {
                throw new ValidationException(['slug' => 'Bu slug başka bir kategoride kullanılıyor.'], $post);
            }
            throw $e;
        } catch (Throwable $e) {
            $this->cleanup($newPath);
            throw $e;
        }

        // Eski görsel, veritabanı güncellemesi başarılı olduktan sonra diskten silinir.
        if ($existing !== null && !empty($existing['image_path']) && ($newPath !== null || $remove)) {
            $this->uploader->delete((string) $existing['image_path']);
        }

        $this->flash('success', 'Kategori kaydedildi.');

        return $this->redirect('/yonetim/kategoriler');
    }

    /**
     * Tek yerde doğrulama + normalleştirme.
     *
     * @param array<string,mixed> $post
     * @param array<string,mixed>|null $existing
     * @param array<string,string> $errors
     * @return array<string,mixed>
     */
    private function normalize(array $post, ?int $id, ?array $existing, array &$errors): array
    {
        $in = [];
        foreach (self::FIELDS as $field) {
            $value = $post[$field] ?? null;
            if ($value !== null && !is_scalar($value)) {
                $errors[$field] = 'Geçersiz değer.';
                $value = null;
            }
            $in[$field] = $value === null ? '' : trim((string) $value);
        }

        $row = [];

        $name = $in['name'];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Kategori adı zorunludur ve en çok 120 karakter olabilir.';
        }
        $row['name'] = $name;

        // Üst kategori: var olmalı, kendisi veya alt dalı olamaz (döngü), derinlik sınırlı.
        $parentId = null;
        if ($in['parent_id'] !== '') {
            $parsed = filter_var($in['parent_id'], FILTER_VALIDATE_INT);
            if ($parsed === false || $parsed < 1 || $this->categories->find((int) $parsed) === null) {
                $errors['parent_id'] = 'Geçerli bir üst kategori seçin.';
            } else {
                $parentId = (int) $parsed;
                $problem = $this->parentProblem($parentId, $id);
                if ($problem !== null) {
                    $errors['parent_id'] = $problem;
                    $parentId = null;
                }
            }
        }
        $row['parent_id'] = $parentId;

        if ($in['slug'] === '') {
            $base = slugify($name);
            $slug = $base === '' ? 'kategori' : $base;
            $i = 2;
            while ($this->categories->slugExists($slug, $id) && $i < 100) {
                $slug = ($base === '' ? 'kategori' : $base) . '-' . $i;
                $i++;
            }
            if ($this->categories->slugExists($slug, $id)) {
                $errors['slug'] = 'Benzersiz slug üretilemedi; elle girin.';
            }
        } else {
            $slug = slugify($in['slug']);
            if ($slug === '' || mb_strlen($slug) > 140) {
                $errors['slug'] = 'Slug geçersiz.';
            } elseif ($this->categories->slugExists($slug, $id)) {
                $errors['slug'] = 'Bu slug başka bir kategoride kullanılıyor.';
            }
        }
        $row['slug'] = $slug;

        if (mb_strlen($in['description']) > 5000) {
            $errors['description'] = 'Açıklama en çok 5000 karakter olabilir.';
        }
        $row['description'] = $in['description'] === '' ? null : $in['description'];

        // Boş sıra: yeni kategoride sona eklenir; düzenlemede üst kategori değişmediyse eski sıra korunur.
        if ($in['sort_order'] === '') {
            $parentChanged = $existing !== null
                && ($existing['parent_id'] === null ? null : (int) $existing['parent_id']) !== $parentId;
            $sort = $existing !== null && !$parentChanged ? (int) $existing['sort_order'] : $this->nextSort($parentId);
        } else {
            $parsedSort = filter_var($in['sort_order'], FILTER_VALIDATE_INT);
            if ($parsedSort === false || $parsedSort < 0 || $parsedSort > 100000) {
                $errors['sort_order'] = 'Sıra 0 ile 100000 arasında bir tam sayı olmalıdır.';
                $sort = 0;
            } else {
                $sort = (int) $parsedSort;
            }
        }
        $row['sort_order'] = $sort;

        $row['is_active'] = filter_var($in['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        foreach (['meta_title' => 160, 'meta_description' => 255] as $field => $max) {
            if (mb_strlen($in[$field]) > $max) {
                $errors[$field] = 'Bu alan en çok ' . $max . ' karakter olabilir.';
            }
            $row[$field] = $in[$field] === '' ? null : $in[$field];
        }

        return $row;
    }

    /** Üst kategori seçimi geçersizse Türkçe hata metni, uygunsa null döner. */
    private function parentProblem(int $parentId, ?int $selfId): ?string
    {
        if ($selfId !== null && $parentId === $selfId) {
            return 'Bir kategori kendi üst kategorisi olamaz.';
        }

        // Yukarı doğru yürünür: kendi kimliğimize rastlanırsa seçilen üst, kendi alt dalımızdadır.
        $depth = 1;
        $cursor = $this->categories->find($parentId);
        while ($cursor !== null) {
            if ($selfId !== null && (int) $cursor['id'] === $selfId) {
                return 'Üst kategori, kendi alt kategorilerinden biri olamaz.';
            }
            if ($cursor['parent_id'] === null) {
                break;
            }
            $depth++;
            if ($depth > self::MAX_DEPTH) {
                return 'Kategori ağacı en çok ' . self::MAX_DEPTH . ' seviye olabilir.';
            }
            $cursor = $this->categories->find((int) $cursor['parent_id']);
        }

        return null;
    }

    private function nextSort(?int $parentId): int
    {
        $max = 0;
        foreach ($this->categories->all(['parent_id' => $parentId]) as $sibling) {
            $max = max($max, (int) $sibling['sort_order']);
        }

        return $max + 1;
    }

    /**
     * Düz kategori listesini ağaç sırasına dizer (üst -> alt, sort_order, id) ve derinlik ekler.
     * $exclude içindeki kimlikler ve onların alt dalları dışarıda bırakılır.
     *
     * @param list<array<string,mixed>> $rows
     * @param array<int,bool> $exclude
     * @return list<array<string,mixed>>
     */
    private function flatten(array $rows, array $exclude = []): array
    {
        $ids = [];
        foreach ($rows as $r) {
            $ids[(int) $r['id']] = true;
        }

        $children = [];
        foreach ($rows as $r) {
            $pid = $r['parent_id'] === null ? 0 : (int) $r['parent_id'];
            // Üstü silinmiş/yok kategoriler kök gibi gösterilir; kaybolmaz.
            if ($pid !== 0 && !isset($ids[$pid])) {
                $pid = 0;
            }
            $children[$pid][] = $r;
        }
        foreach ($children as $pid => $list) {
            usort($list, static fn (array $a, array $b): int => [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']]);
            $children[$pid] = $list;
        }

        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $children, $exclude): void {
            if ($depth > 10) {
                return;
            }
            $list = $children[$parent] ?? [];
            $count = count($list);
            foreach ($list as $i => $r) {
                $cid = (int) $r['id'];
                if (isset($exclude[$cid])) {
                    continue;
                }
                $out[] = [
                    'id'          => $cid,
                    'name'        => (string) $r['name'],
                    'slug'        => (string) $r['slug'],
                    'parent_id'   => $r['parent_id'] === null ? null : (int) $r['parent_id'],
                    'image_path'  => $r['image_path'] === null ? null : (string) $r['image_path'],
                    'sort_order'  => (int) $r['sort_order'],
                    'is_active'   => (int) $r['is_active'] === 1,
                    'depth'       => $depth,
                    'child_count' => count($children[$cid] ?? []),
                    'is_first'    => $i === 0,
                    'is_last'     => $i === $count - 1,
                ];
                $walk($cid, $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }

    /** @param array<string,mixed> $category */
    private function renderForm(array $category, bool $isEdit): Response
    {
        // Üst kategori seçeneklerinden kendisi ve alt dalı çıkarılır (arayüzde döngü seçilemez).
        $exclude = $isEdit ? [(int) $category['id'] => true] : [];

        return $this->view('admin.categories.form', [
            'title'         => $isEdit ? 'Kategoriyi düzenle' : 'Yeni kategori',
            'isEdit'        => $isEdit,
            'category'      => $category,
            'formAction'    => $isEdit ? '/yonetim/kategoriler/' . (int) $category['id'] : '/yonetim/kategoriler',
            'parentOptions' => array_map(
                static fn (array $r): array => ['id' => $r['id'], 'name' => $r['name'], 'depth' => $r['depth']],
                $this->flatten($this->categories->all(), $exclude)
            ),
        ]);
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $category = $this->categories->find($id);
        if ($category === null) {
            throw new HttpException(404, 'Kategori bulunamadı.');
        }

        return $category;
    }

    private function cleanup(?string $path): void
    {
        if ($path !== null) {
            $this->uploader->delete($path);
        }
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private function pick(array $source): array
    {
        $out = [];
        foreach (self::AUDIT_FIELDS as $k) {
            $out[$k] = $source[$k] ?? null;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,mixed> $new
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private function diff(array $old, array $new): array
    {
        $o = [];
        $n = [];
        foreach (self::AUDIT_FIELDS as $k) {
            $ov = $old[$k] ?? null;
            $nv = $new[$k] ?? null;
            if ((string) $ov !== (string) $nv) {
                $o[$k] = $ov;
                $n[$k] = $nv;
            }
        }

        return [$o, $n];
    }

    /** @return array<string,mixed> */
    private function blankCategory(): array
    {
        return [
            'id' => null, 'name' => '', 'slug' => '', 'parent_id' => null, 'description' => '', 'sort_order' => '',
            'is_active' => 1, 'meta_title' => '', 'meta_description' => '', 'image_path' => null,
        ];
    }
}