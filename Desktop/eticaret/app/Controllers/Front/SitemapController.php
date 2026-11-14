<?php // app/Controllers/Front/SitemapController.php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Region;
use XMLWriter;

/**
 * GET /sitemap.xml: ana sayfa, kategoriler, yöreler, aktif ürünler, aktif sayfalar.
 */
final class SitemapController extends BaseController
{
    private const MAX_URLS = 50000; // sitemap protokol sınırı

    public function __construct(
        private readonly Category $categories = new Category(),
        private readonly Region $regions = new Region(),
        private readonly Product $products = new Product(),
        private readonly Page $pages = new Page(),
    ) {
    }

    public function index(Request $request): Response
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $entries = [];

        $entries[] = ['loc' => $base . '/', 'lastmod' => null];
        $entries[] = ['loc' => $base . '/urunler', 'lastmod' => null];

        $this->collectCategories($this->categories->activeTree(), $base, $entries);

        foreach ($this->regions->activeAll() as $region) {
            $entries[] = ['loc' => $base . '/yore/' . rawurlencode((string) $region['slug']), 'lastmod' => null];
        }

        // Soft delete'li kayıtlar model tarafından zaten dışlanır.
        foreach ($this->products->all(['is_active' => 1], null, self::MAX_URLS) as $product) {
            $entries[] = [
                'loc' => $base . '/urun/' . rawurlencode((string) $product['slug']),
                'lastmod' => $this->toDate($product['updated_at'] ?? null),
            ];
        }

        foreach ($this->pages->all(['is_active' => 1]) as $page) {
            $entries[] = [
                'loc' => $base . '/sayfa/' . rawurlencode((string) $page['slug']),
                'lastmod' => $this->toDate($page['updated_at'] ?? null),
            ];
        }

        $entries = array_slice($entries, 0, self::MAX_URLS);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($entries as $entry) {
            $xml->startElement('url');
            $xml->writeElement('loc', $entry['loc']);
            if ($entry['lastmod'] !== null) {
                $xml->writeElement('lastmod', $entry['lastmod']);
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();

        return Response::text($xml->outputMemory())
            ->withHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    /**
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,array{loc:string,lastmod:?string}> $entries
     */
    private function collectCategories(array $nodes, string $base, array &$entries): void
    {
        foreach ($nodes as $node) {
            $entries[] = [
                'loc' => $base . '/kategori/' . rawurlencode((string) $node['slug']),
                'lastmod' => $this->toDate($node['updated_at'] ?? null),
            ];
            $children = $node['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $this->collectCategories($children, $base, $entries);
            }
        }
    }

    private function toDate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : date('Y-m-d', $ts);
    }
}