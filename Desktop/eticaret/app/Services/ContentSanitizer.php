<?php
// app/Services/ContentSanitizer.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use DOMDocument;
use DOMElement;
use HTMLPurifier;
use HTMLPurifier_Config;
use RuntimeException;

/**
 * CMS HTML temizleyici (HTMLPurifier sarmalayıcı).
 * Güvenlik: HTML kabul eden tek alan (pages.content) kaydedilmeden ve önizlenmeden
 * önce buradan geçer; beyaz liste dışındaki her etiket/öznitelik atılır (XSS).
 */
final class ContentSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    /** İzinli etiket/öznitelik beyaz listesi. */
    private const ALLOWED = 'p,br,h2,h3,h4,ul,ol,li,strong,em,blockquote,'
        . 'a[href|title|rel|target],img[src|alt],'
        . 'table,thead,tbody,tr,th,td';

    /**
     * Ham HTML'i temizler ve güvenli HTML döndürür.
     */
    public function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $purified = $this->purifier()->purify($html);

        return $this->postProcess($purified);
    }

    private function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        if (!class_exists(HTMLPurifier::class)) {
            // Composer bağımlılığı eksik: güvenli varsayılan olarak çalışmayı durdur.
            Logger::error('HTMLPurifier bulunamadı (composer require ezyang/htmlpurifier).');
            throw new RuntimeException('HTML temizleyici kullanılamıyor.');
        }

        $cacheDir = Config::basePath('storage/cache/purifier');
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
            throw new RuntimeException('Temizleyici önbellek dizini oluşturulamadı.');
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('Cache.SerializerPath', $cacheDir);
        // Yalnızca bu şemalar; javascript:, data: vb. düşer.
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'tel' => true,
        ]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('AutoFormat.AutoParagraph', false);

        self::$purifier = new HTMLPurifier($config);

        return self::$purifier;
    }

    /**
     * Purifier sonrası ikinci savunma hattı: img src kısıtı, '//' ile başlayan
     * (protokol-bağıl) adresler ve harici bağlantılara rel.
     */
    private function postProcess(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8" ?><div id="ym-root">' . $html . '</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('ym-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        // Görseller: yalnızca /uploads/ (tek eğik çizgi) veya https.
        $images = [];
        foreach ($doc->getElementsByTagName('img') as $img) {
            $images[] = $img;
        }
        foreach ($images as $img) {
            $src = trim($img->getAttribute('src'));
            $isLocal = str_starts_with($src, '/uploads/') && !str_contains($src, '..');
            $isHttps = (bool) preg_match('#^https://[^\s/]+#i', $src);
            if (!$isLocal && !$isHttps) {
                $img->parentNode?->removeChild($img);
                continue;
            }
            $img->setAttribute('alt', $img->getAttribute('alt'));
        }

        // Bağlantılar: protokol-bağıl adres yasak; harici bağlantıya rel zorunlu.
        $links = [];
        foreach ($doc->getElementsByTagName('a') as $a) {
            $links[] = $a;
        }
        foreach ($links as $a) {
            $href = trim($a->getAttribute('href'));
            if (str_starts_with($href, '//')) {
                $a->removeAttribute('href');
                continue;
            }
            if (preg_match('#^https?://#i', $href) === 1) {
                $a->setAttribute('rel', 'noopener noreferrer');
            } elseif ($a->getAttribute('target') !== '') {
                // Dahili bağlantıda da target varsa tabnabbing'e karşı rel ekle.
                $a->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }
}