<?php
declare(strict_types=1);

// app/Controllers/Front/PageController.php

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Page;

/**
 * CMS sayfaları (KVKK, mesafeli satış, çerez vb.).
 */
final class PageController extends BaseController
{
    public function show(Request $request, string $slug): Response
    {
        // Pasif veya silinmiş sayfa yok sayılır: findActiveBySlug yalnızca aktif+silinmemiş döner.
        $page = (new Page())->findActiveBySlug($slug);
        if ($page === null) {
            throw new HttpException(404, 'Sayfa bulunamadı.');
        }

        $metaTitle = trim((string) ($page['meta_title'] ?? ''));

        return $this->view('front.page', [
            'page' => $page,
            'title' => $metaTitle !== '' ? $metaTitle : (string) $page['title'],
            'metaDescription' => (string) ($page['meta_description'] ?? ''),
            'canonical' => url('/sayfa/' . $page['slug']),
            'bodyClass' => 'page-cms',
        ]);
    }
}
