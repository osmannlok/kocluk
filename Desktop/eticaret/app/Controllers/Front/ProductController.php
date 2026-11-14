<?php // app/Controllers/Front/ProductController.php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Region;
use App\Models\Setting;
use App\Services\CatalogService;

/**
 * Ürün liste (/urunler, /yore/{slug}) ve detay (/urun/{slug}) sayfaları.
 * Controller yalnızca isteği alır, servisi çağırır, view'a SEO alanlarıyla birlikte verir.
 */
final class ProductController extends BaseController
{
    public function __construct(
        private readonly CatalogService $catalog = new CatalogService(),
        private readonly Region $regions = new Region(),
        private readonly Category $categories = new Category(),
        private readonly Setting $settings = new Setting(),
    ) {
    }

    public function index(Request $request): Response
    {
        $query = $this->queryArray($request);
        $region = $this->regionFromQuery($query);

        $result = $this->catalog->listProducts($query, null, $region !== null ? (int) $region['id'] : null);
        $this->assertPageExists($result['products']);

        $params = CatalogService::buildQuery($result['filters'], $result['sort']);
        if ($region !== null) {
            $params['yore'] = (string) $region['slug'];
        }

        $siteName = (string) $this->settings->get('site.name', 'Yöresel Market');

        return $this->view('front.products.index', [
            'title' => 'Tüm Ürünler | ' . $siteName,
            'metaDescription' => 'Türkiye\'nin yöresel lezzetlerini keşfedin: bal, peynir, kuru yemiş, reçel ve daha fazlası.',
            'canonical' => $this->canonical('/urunler', (int) $result['filters']['page']),
            'bodyClass' => 'page-catalog',
            'scripts' => ['catalog.js', 'search.js'],
            'heading' => 'Tüm Ürünler',
            'description' => null,
            'category' => null,
            'subcategories' => [],
            'banners' => [],
            'crumbs' => [['Ana Sayfa', '/'], ['Ürünler', null]],
            'products' => $result['products'],
            'pager' => $this->pager($result['products']),
            'filters' => $result['filters'],
            'sort' => $result['sort'],
            'queryParams' => $params,
            'baseUrl' => '/urunler',
            'categoryTree' => $this->categories->activeTree(),
            'regions' => $this->regions->activeAll(),
            'activeCategoryId' => null,
            'activeRegionSlug' => $region !== null ? (string) $region['slug'] : null,
            'regionLocked' => false,
        ]);
    }

    public function byRegion(Request $request, string $slug): Response
    {
        // Pasif/olmayan yöre 404 (model yalnızca aktif yöre döner).
        $region = $this->regions->findBySlug($slug);
        if ($region === null) {
            throw new HttpException(404, 'Yöre bulunamadı.');
        }

        $query = $this->queryArray($request);
        $result = $this->catalog->listProducts($query, null, (int) $region['id']);
        $this->assertPageExists($result['products']);

        $params = CatalogService::buildQuery($result['filters'], $result['sort']);
        $siteName = (string) $this->settings->get('site.name', 'Yöresel Market');
        $regionName = (string) $region['name'];
        $path = '/yore/' . $slug;

        return $this->view('front.products.index', [
            'title' => $regionName . ' Yöresi Ürünleri | ' . $siteName,
            'metaDescription' => $regionName . ' yöresine ait yöresel gıda ürünlerini inceleyin.',
            'canonical' => $this->canonical($path, (int) $result['filters']['page']),
            'bodyClass' => 'page-catalog',
            'scripts' => ['catalog.js', 'search.js'],
            'heading' => $regionName . ' Yöresi',
            'description' => null,
            'category' => null,
            'subcategories' => [],
            'banners' => [],
            'crumbs' => [['Ana Sayfa', '/'], ['Ürünler', '/urunler'], [$regionName, null]],
            'products' => $result['products'],
            'pager' => $this->pager($result['products']),
            'filters' => $result['filters'],
            'sort' => $result['sort'],
            'queryParams' => $params,
            'baseUrl' => $path,
            'categoryTree' => $this->categories->activeTree(),
            'regions' => $this->regions->activeAll(),
            'activeCategoryId' => null,
            'activeRegionSlug' => (string) $region['slug'],
            'regionLocked' => true,
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        // Pasif/silinmiş ürün için model null döner => 404.
        $detail = $this->catalog->productDetail($slug);
        if ($detail === null) {
            throw new HttpException(404, 'Ürün bulunamadı.');
        }

        $product = $detail['product'];
        $siteName = (string) $this->settings->get('site.name', 'Yöresel Market');
        $name = (string) $product['name'];

        $description = trim((string) ($product['meta_description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($product['short_description'] ?? ''));
        }
        if ($description === '') {
            $plain = preg_replace('/\s+/u', ' ', strip_tags((string) ($product['description'] ?? '')));
            $description = trim(is_string($plain) ? $plain : '');
        }
        $description = mb_substr($description, 0, 160, 'UTF-8');

        $crumbs = [['Ana Sayfa', '/'], ['Ürünler', '/urunler']];
        foreach ($detail['category_trail'] as $c) {
            $crumbs[] = [$c['name'], '/kategori/' . $c['slug']];
        }
        $crumbs[] = [$name, null];

        $metaTitle = trim((string) ($product['meta_title'] ?? ''));
        $data = [
            'title' => ($metaTitle !== '' ? $metaTitle : $name) . ' | ' . $siteName,
            'metaDescription' => $description,
            'canonical' => url('/urun/' . $slug),
            'bodyClass' => 'page-product',
            'scripts' => ['catalog.js', 'search.js'],
            'product' => $product,
            'images' => $detail['images'],
            'variants' => $detail['variants'],
            'defaultVariantId' => $detail['default_variant_id'],
            'related' => $detail['related'],
            'crumbs' => $crumbs,
            'freeThreshold' => (string) $this->settings->get('shipping.free_threshold', '0.00'),
        ];

        $first = $detail['images'][0]['path'] ?? null;
        $og = is_string($first) ? $this->absoluteUrl($first) : null;
        if ($og !== null) {
            $data['ogImage'] = $og;
        }

        return $this->view('front.products.show', $data);
    }

    /** @return array<string,mixed> */
    private function queryArray(Request $request): array
    {
        $q = $request->query();

        return is_array($q) ? $q : [];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>|null
     */
    private function regionFromQuery(array $query): ?array
    {
        $slug = $query['yore'] ?? null;
        if (!is_string($slug) || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        return $this->regions->findBySlug($slug);
    }

    /** @param array<string,mixed> $products */
    private function assertPageExists(array $products): void
    {
        if ((int) $products['total'] > 0 && (int) $products['page'] > (int) $products['pages']) {
            throw new HttpException(404, 'Sayfa bulunamadı.');
        }
    }

    /**
     * @param array<string,mixed> $products
     * @return array{page:int,pages:int,total:int}
     */
    private function pager(array $products): array
    {
        return [
            'page' => (int) $products['page'],
            'pages' => (int) $products['pages'],
            'total' => (int) $products['total'],
        ];
    }

    private function canonical(string $path, int $page): string
    {
        return url($path) . ($page > 1 ? '?page=' . $page : '');
    }

    private function absoluteUrl(string $path): ?string
    {
        if ($path !== '' && $path[0] === '/' && !str_starts_with($path, '//')) {
            return url($path);
        }

        return preg_match('#^https?://#i', $path) === 1 ? $path : null;
    }
}