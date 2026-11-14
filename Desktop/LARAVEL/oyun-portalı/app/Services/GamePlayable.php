<?php

declare(strict_types=1);

namespace App\Services;

class GamePlayable
{
    private const PLAYABLE_SLUGS = [
        'sayi-eslestirme' => 'sayi-eslestirme',
        'toplama-hiz-yarisi' => 'toplama-hiz-yarisi',
        'carpim-tablosu-kartlari' => 'carpim-tablosu-kartlari',
        'hafiza-kartlari-pro' => 'hafiza-kartlari-pro',
        'kelime-bulmaca-plus' => 'kelime-bulmaca-plus',
        'mantik-dizisi-tamamlama' => 'mantik-dizisi-tamamlama',
        'farki-bul' => 'farki-bul',
        'sekil-siralama' => 'sekil-siralama',
        'hedef-sayiya-ulas' => 'hedef-sayiya-ulas',
        'dikkat-suresi-oyunu' => 'dikkat-suresi-oyunu',
    ];

    public static function hasPlayableView(string $slug): bool
    {
        return isset(self::PLAYABLE_SLUGS[$slug]);
    }

    public static function viewPath(string $slug): ?string
    {
        if (!self::hasPlayableView($slug)) {
            return null;
        }

        $file = BASE_PATH . '/views/games/play/' . self::PLAYABLE_SLUGS[$slug] . '.php';

        return is_file($file) ? $file : null;
    }
}