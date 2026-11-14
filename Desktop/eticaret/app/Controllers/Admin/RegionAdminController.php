<?php
// app/Controllers/Admin/RegionAdminController.php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Product;
use App\Models\Region;
use PDOException;

/**
 * Yönetim: yöre listesi, ekleme, düzenleme ve silme.
 * SAPMA: regions tablosunda deleted_at yoktur (soft delete mümkün değil); silme kalıcıdır, bu yüzden
 * ürün bağlıysa engellenir ve silinen kayıt denetim günlüğüne yazılır. Planda yöre servisi yok:
 * iş kuralları burada, yalnızca model çağrıları ve transaction ile uygulanır. SQL yoktur.
 */
final class RegionAdminController extends AdminBaseController
{
    private const AUDIT_FIELDS = ['name', 'slug', 'sort_order', 'is_active'];

    private Region $regions;
    private Product $products;

    public function __construct(?Region $regions = null, ?Product $products = null)
    {
        if (method_exists(parent::class, '__construct')) {
            parent::__construct();
        }
        $this->regions = $regions ?? new Region();
        $this->products = $products ?? new Product();
    }

    public function index(Request $request): Response
    {
        $this->require('category.manage');

        return $this->renderIndex(null);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->require('category.manage');

        return $this->renderIndex($this->findOr404($id));
    }

    public function store(Request $request): Response
    {
        $this->require('category.manage');

        return $this->persist($request, null);
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
        // Silme yetkisi: yöre için ayrı kod olmadığından yalnızca admin'de bulunan product.delete de istenir.
        $this->require('product.delete');

        $region = $this->findOr404($id);

        // Ürün bağlıysa silinmez (FK SET NULL ürünlerin yöresini sessizce boşaltmasın).
        if ($this->products->count(['region_id' => $id]) > 0) {
            $this->flash('error', 'Bu yöreye bağlı ürünler var; önce ürünlerin yöresini değiştirin.');

            return $this->redirect('/yonetim/yoreler');
        }

        Database::instance()->transaction(function () use ($id, $region): void {
            $this->regions->forceDelete($id);
            $this->audit('region.delete', 'region', $id, $this->pick($region), ['deleted' => true]);
        });

        $this->flash('success', 'Yöre silindi.');

        return $this->redirect('/yonetim/yoreler');
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
        if ($errors !== []) {
            throw new ValidationException($errors, $post);
        }

        try {
            Database::instance()->transaction(function () use ($id, $existing, $row): void {
                if ($existing === null) {
                    $newId = $this->regions->create($row);
                    $this->audit('region.create', 'region', $newId, null, $this->pick($row));

                    return;
                }
                $this->regions->update((int) $id, $row);
                [$oldDiff, $newDiff] = $this->diff($existing, $row);
                $this->audit('region.update', 'region', (int) $id, $oldDiff, $newDiff);
            });
        } catch (PDOException $e) {
            // Eşzamanlı kayıtta UNIQUE(slug) ihlali doğrulama hatası olarak döner.
            if ((string) $e->getCode() === '23000' && stripos($e->getMessage(), 'Duplicate') !== false) {
                throw new ValidationException(['slug' => 'Bu slug başka bir yörede kullanılıyor.'], $post);
            }
            throw $e;
        }

        $this->flash('success', 'Yöre kaydedildi.');

        return $this->redirect('/yonetim/yoreler');
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
        foreach (['name', 'slug', 'sort_order', 'is_active'] as $field) {
            $value = $post[$field] ?? null;
            if ($value !== null && !is_scalar($value)) {
                $errors[$field] = 'Geçersiz değer.';
                $value = null;
            }
            $in[$field] = $value === null ? '' : trim((string) $value);
        }

        $name = $in['name'];
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Yöre adı zorunludur ve en çok 100 karakter olabilir.';
        }

        // Slug: boşsa addan üretilir ve benzersizleştirilir; elle girilmişse çakışma hatadır.
        if ($in['slug'] === '') {
            $base = slugify($name);
            $base = $base === '' ? 'yore' : $base;
            $slug = $base;
            $i = 2;
            while ($this->slugTaken($slug, $id) && $i < 100) {
                $slug = $base . '-' . $i;
                $i++;
            }
            if ($this->slugTaken($slug, $id)) {
                $errors['slug'] = 'Benzersiz slug üretilemedi; elle girin.';
            }
        } else {
            $slug = slugify($in['slug']);
            if ($slug === '' || mb_strlen($slug) > 120) {
                $errors['slug'] = 'Slug geçersiz.';
            } elseif ($this->slugTaken($slug, $id)) {
                $errors['slug'] = 'Bu slug başka bir yörede kullanılıyor.';
            }
        }

        // Boş sıra: yeni yörede sona eklenir, düzenlemede eski sıra korunur.
        if ($in['sort_order'] === '') {
            $sort = $existing !== null ? (int) $existing['sort_order'] : $this->nextSort();
        } else {
            $parsed = filter_var($in['sort_order'], FILTER_VALIDATE_INT);
            if ($parsed === false || $parsed < 0 || $parsed > 100000) {
                $errors['sort_order'] = 'Sıra 0 ile 100000 arasında bir tam sayı olmalıdır.';
                $sort = 0;
            } else {
                $sort = (int) $parsed;
            }
        }

        return [
            'name'       => $name,
            'slug'       => $slug,
            'sort_order' => $sort,
            'is_active'  => filter_var($in['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ];
    }

    /** Region::findBySlug yalnızca aktifleri döner; pasifler de UNIQUE kapsamında olduğundan BaseModel::findBy kullanılır. */
    private function slugTaken(string $slug, ?int $exceptId): bool
    {
        $found = $this->regions->findBy('slug', $slug);

        return $found !== null && ($exceptId === null || (int) $found['id'] !== $exceptId);
    }

    private function nextSort(): int
    {
        $max = 0;
        foreach ($this->regions->all() as $region) {
            $max = max($max, (int) $region['sort_order']);
        }

        return $max + 1;
    }

    /** @param array<string,mixed>|null $editing */
    private function renderIndex(?array $editing): Response
    {
        $rows = $this->regions->all();
        usort($rows, static fn (array $a, array $b): int => [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']]);

        $regions = array_map(static fn (array $r): array => [
            'id'         => (int) $r['id'],
            'name'       => (string) $r['name'],
            'slug'       => (string) $r['slug'],
            'sort_order' => (int) $r['sort_order'],
            'is_active'  => (int) $r['is_active'] === 1,
        ], $rows);

        $isEdit = $editing !== null;

        return $this->view('admin.regions.index', [
            'title'      => 'Yöreler',
            'regions'    => $regions,
            'isEdit'     => $isEdit,
            'region'     => $editing ?? ['id' => null, 'name' => '', 'slug' => '', 'sort_order' => '', 'is_active' => 1],
            'formAction' => $isEdit ? '/yonetim/yoreler/' . (int) $editing['id'] : '/yonetim/yoreler',
        ]);
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $region = $this->regions->find($id);
        if ($region === null) {
            throw new HttpException(404, 'Yöre bulunamadı.');
        }

        return $region;
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
}