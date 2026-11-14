<?php // app/Controllers/Front/SearchController.php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Setting;
use App\Services\CatalogService;

/**
 * Arama sonuç sayfası (/ara) ve otomatik tamamlama JSON ucu (/ara/oneri).
 */
final class SearchController extends BaseController
{
    public function __construct(
        private readonly CatalogService $catalog = new CatalogService(),
        private readonly Category $categories = new Category(),
        private readonly Setting $settings = new Setting(),
    ) {
    }

    public function index(Request $request): Response
    {
        $query = $request->query();
        $query = is_array($query) ? $query : [];

        $rawQ = isset($query['q']) && is_string($query['q']) ? trim($query['q']) : '';
        $q = CatalogService::normalizeQuery($rawQ);
        // Dolu ama 2 karakterden kısa arama: sonuç yerine uyarı gösterilir.
        $tooShort = $rawQ !== '' && $q === '';

        if ($q !== '') {
            $result = $this->catalog->listProducts(['q' => $q] + $query);
            $products = $result['products'];
            $filters = $result['filters'];
            $sort = $result['sort'];
            if ((int) $products['total'] > 0 && (int) $products['page'] > (int) $products['pages']) {
                throw new HttpException(404, 'Sayfa bulunamadı.');
            }
        } else {
            $products = ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 12, 'pages' => 1];
            $filters = ['q' => ''];
            $sort = 'newest';
        }

        $params = $q !== '' ? CatalogService::buildQuery(['q' => $q], $sort) : [];
        $siteName = (string) $this->settings->get('site.name', 'Yöresel Market');

        return $this->view('front.search', [
            'title' => ($q !== '' ? 'Arama: ' . $q : 'Ürün Ara') . ' | ' . $siteName,
            'metaDescription' => 'Yöresel ürünlerde arama yapın.',
            'canonical' => url('/ara'),
            'bodyClass' => 'page-search',
            'scripts' => ['catalog.js', 'search.js'],
            'q' => $q,
            'rawQ' => $rawQ,
            'tooShort' => $tooShort,
            'products' => $products,
            'pager' => [
                'page' => (int) $products['page'],
                'pages' => (int) $products['pages'],
                'total' => (int) $products['total'],
            ],
            'filters' => $filters,
            'sort' => $sort,
            'queryParams' => $params,
            'suggestCategories' => array_slice($this->categories->activeTree(), 0, 8),
            'crumbs' => [['Ana Sayfa', '/'], ['Arama', null]],
        ]);
    }

    public function suggest(Request $request): Response
    {
        $q = $request->query('q', '');
        $items = [];
        foreach ($this->catalog->suggest(is_string($q) ? $q : '') as $row) {
            $items[] = [
                'name' => $row['name'],
                'slug' => $row['slug'],
                'image' => $row['image'],
                'url' => '/urun/' . $row['slug'],
            ];
        }

        return $this->ok(['items' => $items]);
    }
}