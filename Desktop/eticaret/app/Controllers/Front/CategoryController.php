<?php // app/Controllers/Front/CategoryController.php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Region;
use App\Models\Setting;
use App\Services\CatalogService;

/**
 * Kategori sayfası: alt kategori çipleri, breadcrumb, ürün listesi (alt kategoriler dahil), category_top banner'ı.
 */
final class CategoryController extends BaseController
{
    public function __construct(
        private readonly CatalogService $catalog = new CatalogService(),
        private readonly Category $categories = new Category(),
        private readonly Region $regions = new Region(),
        private readonly Banner $banners = new Banner(),
        private readonly Setting $settings = new Setting(),
    ) {
    }

    public function show(Request $request, string $slug): Response
    {
        // findBySlug yalnızca aktif kategori döner; pasif/silinmiş => 404.
        $category = $this->categories->findBySlug($slug);
        if ($category === null) {
            throw new HttpException(404, 'Kategori bulunamadı.');
        }
        $categoryId = (int) $category['id'];

        $query = $request->query();
        $query = is_array($query) ? $query : [];

        $region = null;
        $regionSlug = $query['yore'] ?? null;
        if (is_string($regionSlug) && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $regionSlug) === 1) {
            $region = $this->regions->findBySlug($regionSlug);
        }

        $result = $this->catalog->listProducts($query, $categoryId, $region !== null ? (int) $region['id'] : null);
        $products = $result['products'];
        if ((int) $products['total'] > 0 && (int) $products['page'] > (int) $products['pages']) {
            throw new HttpException(404, 'Sayfa bulunamadı.');
        }

        $params = CatalogService::buildQuery($result['filters'], $result['sort']);
        if ($region !== null) {
            $params['yore'] = (string) $region['slug'];
        }

        $crumbs = [['Ana Sayfa', '/']];
        $trail = $this->catalog->categoryTrail($category);
        $lastIndex = count($trail) - 1;
        foreach ($trail as $i => $c) {
            $crumbs[] = [$c['name'], $i === $lastIndex ? null : '/kategori/' . $c['slug']];
        }

        $siteName = (string) $this->settings->get('site.name', 'Yöresel Market');
        $name = (string) $category['name'];
        $metaTitle = trim((string) ($category['meta_title'] ?? ''));
        $metaDescription = trim((string) ($category['meta_description'] ?? ''));
        if ($metaDescription === '') {
            $metaDescription = $name . ' kategorisindeki yöresel ürünleri inceleyin.';
        }

        $path = '/kategori/' . $slug;
        $page = (int) $result['filters']['page'];
        $data = [
            'title' => ($metaTitle !== '' ? $metaTitle : $name) . ' | ' . $siteName,
            'metaDescription' => mb_substr($metaDescription, 0, 160, 'UTF-8'),
            'canonical' => url($path) . ($page > 1 ? '?page=' . $page : ''),
            'bodyClass' => 'page-catalog page-category',
            'scripts' => ['catalog.js', 'search.js'],
            'heading' => $name,
            'description' => $category['description'] ?? null,
            'category' => $category,
            'subcategories' => $this->categories->childrenOf($categoryId),
            'banners' => $this->banners->activeByPosition('category_top'),
            'crumbs' => $crumbs,
            'products' => $products,
            'pager' => [
                'page' => (int) $products['page'],
                'pages' => (int) $products['pages'],
                'total' => (int) $products['total'],
            ],
            'filters' => $result['filters'],
            'sort' => $result['sort'],
            'queryParams' => $params,
            'baseUrl' => $path,
            'categoryTree' => $this->categories->activeTree(),
            'regions' => $this->regions->activeAll(),
            'activeCategoryId' => $categoryId,
            'activeRegionSlug' => $region !== null ? (string) $region['slug'] : null,
            'regionLocked' => false,
        ];

        $image = $category['image_path'] ?? null;
        if (is_string($image) && $image !== '' && $image[0] === '/' && !str_starts_with($image, '//')) {
            $data['ogImage'] = url($image);
        }

        return $this->view('front.products.index', $data);
    }
}