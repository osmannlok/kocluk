<?php
// app/Controllers/Admin/ProductAdminController.php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Services\ImageUploadService;
use App\Services\ProductAdminService;
use DomainException;
use Throwable;

/**
 * Yönetim: ürün listesi, oluşturma/düzenleme, silme, aktif/pasif ve görsel uçları.
 * Controller yalnızca isteği doğrular ve servisi çağırır; SQL yoktur.
 * Her metot hem rota middleware'inde hem burada require() ile yetki kontrolü yapar.
 */
final class ProductAdminController extends AdminBaseController
{
    /** Formdan kabul edilen ürün alanları (mass assignment koruması: başka alan servise gitmez). */
    private const PRODUCT_FIELDS = [
        'name', 'slug', 'category_id', 'region_id', 'short_description', 'description',
        'origin_text', 'producer_name', 'ingredients', 'allergen_info', 'storage_conditions',
        'shelf_life_info', 'tax_rate', 'is_active', 'is_featured', 'meta_title', 'meta_description',
    ];

    /** Formdan kabul edilen varyant alanları (onay kutuları hariç). */
    private const VARIANT_FIELDS = [
        'id', 'name', 'sku', 'barcode', 'weight_grams', 'price', 'sale_price',
        'sale_starts_at', 'sale_ends_at', 'stock_quantity', 'low_stock_threshold', 'sort_order',
    ];

    /** Onay kutusu alanları: formda işaretsiz kutu gönderilmez, yani yok = kapalı sayılır. */
    private const VARIANT_FLAGS = ['track_stock', 'is_active', 'is_default'];

    private const MAX_VARIANTS = 50;
    private const MAX_IMAGES = 12;

    // SAPMA: 14-admin-sozlesme'de $pageScripts "asset yolları dizisi" olarak geçer; vendor yollarıyla
    // aynı biçim (/assets/...) kullanıldı. Layout farklı biçim bekliyorsa yalnızca bu sabit değişir.
    private const FORM_SCRIPTS = ['/assets/js/admin-product-form.js'];

    private Product $products;
    private ProductVariant $variants;
    private ProductImage $images;
    private Category $categories;
    private Region $regions;
    private ProductAdminService $service;
    private ImageUploadService $uploader;

    public function __construct(
        ?Product $products = null,
        ?ProductVariant $variants = null,
        ?ProductImage $images = null,
        ?Category $categories = null,
        ?Region $regions = null,
        ?ProductAdminService $service = null,
        ?ImageUploadService $uploader = null
    ) {
        if (method_exists(parent::class, '__construct')) {
            parent::__construct();
        }
        $this->products = $products ?? new Product();
        $this->variants = $variants ?? new ProductVariant();
        $this->images = $images ?? new ProductImage();
        $this->categories = $categories ?? new Category();
        $this->regions = $regions ?? new Region();
        $this->service = $service ?? new ProductAdminService();
        $this->uploader = $uploader ?? new ImageUploadService();
    }

    public function index(Request $request): Response
    {
        $this->require('product.view');

        $filters = $this->readFilters($request);
        $result = $this->products->adminList($filters, $this->currentPage($request), $this->perPage());

        return $this->view('admin.products.index', [
            'title'      => 'Ürünler',
            'products'   => $result['items'],
            'pager'      => $this->pager($result, $request),
            'filters'    => $filters,
            'categories' => $this->categories->allFlat(),
            'regions'    => $this->regions->activeAll(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->require('product.create');

        return $this->renderForm(null, [$this->blankVariant()], []);
    }

    public function store(Request $request): Response
    {
        $this->require('product.create');

        return $this->persist($request, null);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->require('product.update');

        $product = $this->findOr404($id);
        $variants = $this->variants->forProduct($id, false);
        $images = array_map([$this, 'presentImage'], $this->images->forProduct($id));

        return $this->renderForm($product, $variants, $images);
    }

    public function update(Request $request, int $id): Response
    {
        $this->require('product.update');
        $this->findOr404($id);

        return $this->persist($request, $id);
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->require('product.delete');

        // Soft delete: servis önce pasifleştirir, sonra deleted_at yazar ve denetim kaydı düşer.
        $this->service->delete($id, $this->adminId());
        $this->flash('success', 'Ürün silindi.');

        return $this->redirect('/yonetim/urunler');
    }

    public function toggle(Request $request, int $id): Response
    {
        $this->require('product.update');

        $active = $this->service->toggleActive($id, $this->adminId());

        if ($request->wantsJson()) {
            return $this->ok(['id' => $id, 'is_active' => $active]);
        }
        $this->flash('success', $active ? 'Ürün aktifleştirildi.' : 'Ürün pasifleştirildi.');

        return $this->redirect('/yonetim/urunler');
    }

    // ------------------------------------------------------------------
    // Görsel uçları (JSON)
    // ------------------------------------------------------------------

    /**
     * Tek istekte tek dosya (alan adı: image). Çoklu yükleme istemcide ardışık isteklerle yapılır;
     * böylece her dosya kendi hata yanıtını alır ve çok dosyalı $_FILES biçimine bağımlılık olmaz.
     */
    public function uploadImage(Request $request, int $id): Response
    {
        $this->require('product.update');
        $this->findOr404($id);

        $existing = $this->images->forProduct($id);
        if (count($existing) >= self::MAX_IMAGES) {
            $message = 'Bir ürüne en çok ' . self::MAX_IMAGES . ' görsel eklenebilir.';

            return $this->fail('validation', $message, 422, ['image' => $message]);
        }

        $altRaw = $request->post('alt_text');
        $alt = is_string($altRaw) && trim($altRaw) !== '' ? mb_substr(trim($altRaw), 0, 190) : null;

        // Dosya doğrulaması ve yeniden encode ImageUploadService'tedir; ret durumunda 422 fırlatır.
        $stored = $this->uploader->store($request->file('image') ?? ['error' => UPLOAD_ERR_NO_FILE], 'products', ['thumb' => true]);

        $maxSort = 0;
        foreach ($existing as $row) {
            $maxSort = max($maxSort, (int) $row['sort_order']);
        }

        try {
            $imageId = $this->images->create([
                'product_id' => $id,
                'path'       => $stored['path'],
                'alt_text'   => $alt,
                'sort_order' => $existing === [] ? 0 : $maxSort + 1,
                'is_primary' => $existing === [] ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            // Kayıt yazılamadıysa diskte sahipsiz dosya kalmasın.
            $this->uploader->delete($stored['path']);
            throw $e;
        }

        $this->audit('product.image.add', 'product', $id, null, ['image_id' => $imageId, 'path' => $stored['path']]);

        return $this->ok(['image' => $this->presentImage([
            'id'         => $imageId,
            'path'       => $stored['path'],
            'alt_text'   => $alt,
            'sort_order' => $existing === [] ? 0 : $maxSort + 1,
            'is_primary' => $existing === [] ? 1 : 0,
        ])]);
    }

    public function deleteImage(Request $request, int $id, int $imageId): Response
    {
        $this->require('product.update');
        $this->findOr404($id);
        $image = $this->ownedImage($id, $imageId);

        // SAPMA: görsel DB işlemleri planda ayrı servis olmadığından burada, yalnızca model + transaction ile yapılır.
        Database::instance()->transaction(function () use ($id, $imageId, $image): void {
            $this->images->forceDelete($imageId);
            if ((int) $image['is_primary'] === 1) {
                // Ana görsel silindiyse sıradaki görsel ana yapılır.
                $remaining = $this->images->forProduct($id);
                if ($remaining !== []) {
                    $this->images->setPrimary($id, (int) $remaining[0]['id']);
                }
            }
        });

        // Dosya, veritabanı kaydı başarıyla silindikten sonra kaldırılır.
        $this->uploader->delete((string) $image['path']);

        $this->audit(
            'product.image.delete',
            'product',
            $id,
            ['image_id' => $imageId, 'path' => (string) $image['path'], 'is_primary' => (int) $image['is_primary']],
            null
        );

        return $this->ok(['id' => $imageId]);
    }

    public function setPrimaryImage(Request $request, int $id, int $imageId): Response
    {
        $this->require('product.update');
        $this->findOr404($id);
        $this->ownedImage($id, $imageId);

        $this->images->setPrimary($id, $imageId);
        $this->audit('product.image.primary', 'product', $id, null, ['image_id' => $imageId]);

        return $this->ok(['id' => $imageId]);
    }

    public function reorderImages(Request $request, int $id): Response
    {
        $this->require('product.update');
        $this->findOr404($id);

        $order = $request->json()['order'] ?? null;
        if (!is_array($order) || $order === [] || count($order) > self::MAX_IMAGES) {
            return $this->fail('validation', 'Sıralama listesi geçersiz.', 422, ['order' => 'Sıralama listesi geçersiz.']);
        }

        // IDOR: ProductImage::reorder ürün doğrulaması yapmaz; yalnızca bu ürüne ait kimlikler kabul edilir.
        $owned = [];
        foreach ($this->images->forProduct($id) as $row) {
            $owned[(int) $row['id']] = true;
        }

        $map = [];
        foreach (array_values($order) as $position => $value) {
            $imageId = is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
            if ($imageId < 1 || !isset($owned[$imageId]) || isset($map[$imageId])) {
                return $this->fail('validation', 'Sıralama listesi geçersiz.', 422, ['order' => 'Sıralama listesi geçersiz.']);
            }
            $map[$imageId] = $position;
        }

        $this->images->reorder($map);
        $this->audit('product.image.reorder', 'product', $id, null, ['order' => array_keys($map)]);

        return $this->ok(['order' => array_keys($map)]);
    }

    // ------------------------------------------------------------------
    // Özel yardımcılar
    // ------------------------------------------------------------------

    private function persist(Request $request, ?int $productId): Response
    {
        [$data, $variants] = $this->collectInput($request);
        $adminId = $this->adminId();

        try {
            $savedId = $this->service->save($productId, $data, $variants, $adminId);
        } catch (ValidationException $e) {
            // Servis yalnızca ürün alanlarını old'a koyar; varyant satırları da forma geri dönsün.
            throw new ValidationException($e->errors(), $data + ['variants' => $variants]);
        } catch (DomainException $e) {
            // Ürün kaydedildi ama stok hareketi uygulanamadı: yönetici bilgilendirilir.
            $this->flash('error', $e->getMessage());

            return $this->redirect($productId === null
                ? '/yonetim/urunler'
                : '/yonetim/urunler/' . $productId . '/duzenle');
        }

        $this->flash('success', 'Ürün kaydedildi.');

        return $this->redirect('/yonetim/urunler/' . $savedId . '/duzenle');
    }

    /**
     * Form girdisini tek yerde doğrular: beyaz liste alanlar, yalnızca skaler değerler,
     * varyantlar dizi-dizi biçiminde ve en çok MAX_VARIANTS satır. İş kuralları serviste.
     *
     * @return array{0: array<string,mixed>, 1: list<array<string,mixed>>}
     */
    private function collectInput(Request $request): array
    {
        $post = $request->post();
        $post = is_array($post) ? $post : [];
        $errors = [];

        $data = [];
        foreach (self::PRODUCT_FIELDS as $field) {
            $value = $post[$field] ?? null;
            if ($value !== null && !is_scalar($value)) {
                $errors[$field] = 'Geçersiz değer.';
                continue;
            }
            $data[$field] = $value;
        }

        $variants = [];
        $rawVariants = $post['variants'] ?? [];
        if (!is_array($rawVariants)) {
            $errors['variants'] = 'Varyant verisi geçersiz.';
        } elseif (count($rawVariants) > self::MAX_VARIANTS) {
            $errors['variants'] = 'Bir üründe en çok ' . self::MAX_VARIANTS . ' varyant olabilir.';
        } else {
            foreach (array_values($rawVariants) as $i => $row) {
                if (!is_array($row)) {
                    $errors['variants.' . $i . '.name'] = 'Varyant satırı geçersiz.';
                    continue;
                }
                $clean = [];
                foreach (self::VARIANT_FIELDS as $field) {
                    $value = $row[$field] ?? null;
                    if ($value !== null && !is_scalar($value)) {
                        $errors['variants.' . $i . '.' . $field] = 'Geçersiz değer.';
                        continue;
                    }
                    $clean[$field] = $value;
                }
                foreach (self::VARIANT_FLAGS as $flag) {
                    $clean[$flag] = isset($row[$flag]) && is_scalar($row[$flag])
                        && filter_var($row[$flag], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                }
                $variants[] = $clean;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors, $post);
        }

        return [$data, $variants];
    }

    /** @return array<string,mixed> */
    private function readFilters(Request $request): array
    {
        $filters = [];

        $q = $request->query('q');
        if (is_string($q) && trim($q) !== '') {
            $filters['q'] = mb_substr(trim($q), 0, 100);
        }

        foreach (['category_id', 'region_id'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                $filters[$key] = (int) $value;
            }
        }

        foreach (['is_active', 'is_featured'] as $key) {
            $value = $request->query($key);
            if ($value === '0' || $value === '1') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * @param array<string,mixed>|null $product
     * @param list<array<string,mixed>> $variants
     * @param list<array<string,mixed>> $images
     */
    private function renderForm(?array $product, array $variants, array $images): Response
    {
        $isEdit = $product !== null;

        return $this->view('admin.products.form', [
            'title'       => $isEdit ? 'Ürünü düzenle' : 'Yeni ürün',
            'isEdit'      => $isEdit,
            'product'     => $product ?? $this->blankProduct(),
            'formAction'  => $isEdit ? '/yonetim/urunler/' . (int) $product['id'] : '/yonetim/urunler',
            'variants'    => $variants,
            'images'      => $images,
            'imageLimit'  => self::MAX_IMAGES,
            'categories'  => $this->categories->allFlat(),
            'regions'     => $this->regions->activeAll(),
            'pageScripts' => self::FORM_SCRIPTS,
        ]);
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $product = $this->products->find($id);
        if ($product === null) {
            throw new HttpException(404, 'Ürün bulunamadı.');
        }

        return $product;
    }

    /**
     * IDOR koruması: görsel yalnızca kendi ürününün altında işlem görür, aksi halde 404.
     *
     * @return array<string,mixed>
     */
    private function ownedImage(int $productId, int $imageId): array
    {
        $image = $this->images->find($imageId);
        if ($image === null || (int) $image['product_id'] !== $productId) {
            throw new HttpException(404, 'Görsel bulunamadı.');
        }

        return $image;
    }

    private function adminId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw new HttpException(401, 'Oturum gerekli.');
        }

        return $id;
    }

    /**
     * @param array<string,mixed> $image
     * @return array{id:int,path:string,thumb_path:string,alt_text:?string,sort_order:int,is_primary:bool}
     */
    private function presentImage(array $image): array
    {
        $path = (string) $image['path'];
        $thumb = preg_replace('/\.(jpg|png|webp)$/', '_thumb.$1', $path);

        return [
            'id'         => (int) $image['id'],
            'path'       => $path,
            'thumb_path' => is_string($thumb) ? $thumb : $path,
            'alt_text'   => $image['alt_text'] === null ? null : (string) $image['alt_text'],
            'sort_order' => (int) $image['sort_order'],
            'is_primary' => (int) $image['is_primary'] === 1,
        ];
    }

    /** @return array<string,mixed> */
    private function blankProduct(): array
    {
        return [
            'id' => null, 'name' => '', 'slug' => '', 'category_id' => null, 'region_id' => null,
            'short_description' => '', 'description' => '', 'origin_text' => '', 'producer_name' => '',
            'ingredients' => '', 'allergen_info' => '', 'storage_conditions' => '', 'shelf_life_info' => '',
            'tax_rate' => '10.00', 'is_active' => 1, 'is_featured' => 0, 'meta_title' => '', 'meta_description' => '',
        ];
    }

    /** @return array<string,mixed> */
    private function blankVariant(): array
    {
        return [
            'id' => null, 'name' => '', 'sku' => '', 'barcode' => '', 'weight_grams' => '', 'price' => '',
            'sale_price' => '', 'sale_starts_at' => '', 'sale_ends_at' => '', 'stock_quantity' => 0,
            'low_stock_threshold' => 5, 'track_stock' => 1, 'is_default' => 1, 'is_active' => 1, 'sort_order' => 0,
        ];
    }
}