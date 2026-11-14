<?php
declare(strict_types=1);

// app/Controllers/Front/HomeController.php

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Request;
use App\Core\Response;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;

/**
 * Vitrin ana sayfası. Yalnızca veri toplar ve view'a verir; iş mantığı/SQL yok.
 */
final class HomeController extends BaseController
{
    public function index(Request $request): Response
    {
        $banner = new Banner();
        $product = new Product();

        // Yalnızca aktif ve tarih penceresi içindeki bannerlar modelden gelir.
        $heroBanners = $banner->activeByPosition('home_hero');
        $secondaryBanners = $banner->activeByPosition('home_secondary');

        // Üst düzey kategoriler (activeTree kökleri).
        $categories = (new Category())->activeTree();

        $featured = $product->featured(8);
        $newest = $product->listActive([], 'newest', 1, 8);

        return $this->view('front.home', [
            'heroBanners' => $heroBanners,
            'secondaryBanners' => $secondaryBanners,
            'categories' => $categories,
            'featured' => $featured,
            'newest' => $newest['items'] ?? [],
            'regions' => (new Region())->activeAll(),
            'freeThreshold' => (string) (new Setting())->get('shipping.free_threshold', '0'),
            'canonical' => url('/'),
            'bodyClass' => 'page-home',
        ]);
    }
}
