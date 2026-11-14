<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Ana sayfa view'ını döndürür.
     */
    public function index()
    {
        return view('index', [
            'title' => 'Digidotta - Ana Sayfa',
            'og_title' => 'Digidotta - Ana Sayfa',
            'twitter_title' => 'Digidotta - Ana Sayfa'
        ]);
    }

    /**
     * Hakkımızda sayfasını döndürür.
     */
    public function about()
    {
        return view('about', [
            'title' => 'Hakkımızda',
            'og_title' => 'Digidotta - Hakkımızda',
            'twitter_title' => 'Digidotta - Hakkımızda'
        ]);
    }

    /**
     * Hizmetler sayfasını döndürür.
     */
    public function services()
    {
        return view('services', [
            'title' => 'Hizmetler',
            'og_title' => 'Digidotta - Hizmetler',
            'twitter_title' => 'Digidotta - Hizmetler'
        ]);
    }

    /**
     * İletişim sayfasını döndürür.
     */
    public function contact()
    {
        return view('contact', [
            'title' => 'İletişim',
            'og_title' => 'Digidotta - İletişim',
            'twitter_title' => 'Digidotta - İletişim'
        ]);
    }

    /**
     * Blog sayfasını döndürür.
     */
    public function blog()
    {
        return view('blog', [
            'title' => 'Blog',
            'og_title' => 'Digidotta - Blog',
            'twitter_title' => 'Digidotta - Blog'
        ]);
    }
}