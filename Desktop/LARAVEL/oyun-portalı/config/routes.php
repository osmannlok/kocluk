<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\GameController;
use App\Controllers\AuthController;
use App\Controllers\BlogController;
use App\Controllers\FavoriteController;
use App\Controllers\ScoreController;
use App\Controllers\BadgeController;
use App\Controllers\PageController;
use App\Controllers\SitemapController;
use App\Controllers\MembershipController;
use App\Controllers\AdminController;
use App\Controllers\Admin\GameController as AdminGameController;
use App\Controllers\Admin\BlogController as AdminBlogController;
use App\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Controllers\Admin\UserController as AdminUserController;

return [
    'GET' => [
        '/' => [HomeController::class, 'index'],
        '/oyunlar' => [GameController::class, 'index'],
        '/oyunlar/{slug}' => [GameController::class, 'show'],
        '/blog' => [BlogController::class, 'index'],
        '/blog/{slug}' => [BlogController::class, 'show'],
        '/giris' => [AuthController::class, 'loginForm'],
        '/kayit' => [AuthController::class, 'registerForm'],
        '/cikis' => [AuthController::class, 'logout'],
        '/favorilerim' => [FavoriteController::class, 'index'],
        '/skorlarim' => [ScoreController::class, 'index'],
        '/rozetlerim' => [BadgeController::class, 'index'],
        '/uyelik' => [MembershipController::class, 'index'],
        '/sayfa/{slug}' => [PageController::class, 'show'],
        '/sitemap.xml' => [SitemapController::class, 'index'],
        '/admin' => [AdminController::class, 'index'],
        '/admin/oyunlar' => [AdminGameController::class, 'index'],
        '/admin/blog' => [AdminBlogController::class, 'index'],
        '/admin/kategoriler' => [AdminCategoryController::class, 'index'],
        '/admin/kullanicilar' => [AdminUserController::class, 'index'],
    ],
    'POST' => [
        '/giris' => [AuthController::class, 'login'],
        '/kayit' => [AuthController::class, 'register'],
        '/oyunlar/{slug}/favori-ekle' => [FavoriteController::class, 'store'],
        '/oyunlar/{slug}/favori-sil' => [FavoriteController::class, 'destroy'],
        '/oyunlar/{slug}/skor-kaydet' => [ScoreController::class, 'store'],
        '/uyelik/{slug}/abone-ol' => [MembershipController::class, 'subscribe'],
        '/uyelik/{id}/iptal' => [MembershipController::class, 'cancel'],
    ],
];