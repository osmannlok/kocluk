<?php
// app/Services/ImageUploadService.php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\ValidationException;
use App\Models\SecurityEvent;
use GdImage;
use RuntimeException;

/**
 * Görsel yükleme servisi.
 * Güvenlik: dosya içeriği GD ile yeniden encode edilir; böylece EXIF, gömülü betik
 * ve polyglot içerik yok olur. Orijinal dosya adı hiçbir yerde kullanılmaz.
 */
final class ImageUploadService
{
    /** Uzantı => izinli MIME ve getimagesize tipi (beyaz liste). */
    private const TYPES = [
        'jpg'  => ['mime' => 'image/jpeg', 'type' => IMAGETYPE_JPEG, 'canonical' => 'jpg'],
        'jpeg' => ['mime' => 'image/jpeg', 'type' => IMAGETYPE_JPEG, 'canonical' => 'jpg'],
        'png'  => ['mime' => 'image/png', 'type' => IMAGETYPE_PNG, 'canonical' => 'png'],
        'webp' => ['mime' => 'image/webp', 'type' => IMAGETYPE_WEBP, 'canonical' => 'webp'],
    ];

    /** Dekompresyon bombasına karşı üst piksel sınırı. */
    private const MAX_PIXELS = 40000000;

    private const DEFAULT_MAX_WIDTH = 1600;
    private const DEFAULT_THUMB_WIDTH = 400;

    /**
     * @param array<string,mixed> $file  $_FILES girdisi
     * @param array<string,mixed> $opts  max_width:int, thumb:bool, thumb_width:int
     * @return array{path:string,width:int,height:int}
     */
    public function store(array $file, string $subdir, array $opts = []): array
    {
        // Alt klasör adı beyaz liste: dizin gezintisi (../) mümkün değil.
        if (preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#', $subdir) !== 1) {
            throw new \InvalidArgumentException('Geçersiz yükleme klasörü.');
        }
        if (!extension_loaded('gd')) {
            throw new RuntimeException('GD eklentisi yüklü değil.');
        }

        $maxWidth = max(100, min(4000, (int) ($opts['max_width'] ?? self::DEFAULT_MAX_WIDTH)));
        $thumbWidth = max(50, min(1000, (int) ($opts['thumb_width'] ?? self::DEFAULT_THUMB_WIDTH)));
        $makeThumb = (bool) ($opts['thumb'] ?? true);

        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            $this->reject('no_file', 'Lütfen bir görsel seçin.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            $this->reject('upload_error', 'Görsel yüklenemedi.', ['code' => (int) $error]);
        }

        $tmp = $file['tmp_name'] ?? '';
        // Yalnızca PHP'nin gerçekten yüklediği dosya kabul edilir.
        if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
            $this->reject('not_uploaded', 'Geçersiz yükleme.');
        }

        // İstemcinin bildirdiği boyuta değil, diskteki gerçek boyuta güvenilir.
        $size = (int) filesize($tmp);
        $maxBytes = max(1, (int) Config::get('app.upload.max_mb', 5)) * 1024 * 1024;
        if ($size <= 0 || $size > $maxBytes) {
            $this->reject('size', 'Görsel boyutu izin verilen sınırı aşıyor.', ['size' => $size]);
        }

        // Orijinal ad yalnızca uzantı kontrolü için okunur; saklanmaz ve loglanmaz.
        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$ext])) {
            $this->reject('extension', 'Yalnızca JPG, PNG ve WEBP görseller yüklenebilir.');
        }
        $spec = self::TYPES[$ext];

        // MIME, dosya içeriğinden finfo ile okunur (istemci başlığına güvenilmez).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $configMimes = Config::get('app.upload.allowed_mimes', []);
        if ($mime !== $spec['mime']
            || (is_array($configMimes) && $configMimes !== [] && !in_array($mime, $configMimes, true))
        ) {
            $this->reject('mime', 'Dosya türü geçersiz.', ['mime' => $mime]);
        }

        $info = @getimagesize($tmp); // Bozuk dosyada uyarı yerine false dönmesi için bastırıldı.
        if ($info === false || (int) $info[2] !== $spec['type']) {
            $this->reject('not_image', 'Dosya geçerli bir görsel değil.');
        }
        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        if ($srcW < 1 || $srcH < 1 || $srcW * $srcH > self::MAX_PIXELS) {
            $this->reject('dimensions', 'Görsel boyutları geçersiz veya çok büyük.', ['w' => $srcW, 'h' => $srcH]);
        }

        $raw = file_get_contents($tmp);
        $image = $raw === false ? false : @imagecreatefromstring($raw);
        unset($raw);
        if (!$image instanceof GdImage) {
            $this->reject('decode', 'Görsel işlenemedi.');
        }

        $canonical = $spec['canonical'];
        $hasAlpha = $canonical !== 'jpg';

        if ($canonical === 'jpg') {
            $image = $this->applyExifOrientation($image, $tmp);
        }
        $w = imagesx($image);
        $h = imagesy($image);

        // Ana görsel: en çok $maxWidth piksel genişlik.
        if ($w > $maxWidth) {
            $newW = $maxWidth;
            $newH = max(1, (int) round($h * $maxWidth / $w));
            $image = $this->resize($image, $w, $h, $newW, $newH, $hasAlpha);
            $w = $newW;
            $h = $newH;
        }

        $relDir = '/uploads/' . $subdir . '/' . date('Y') . '/' . date('m');
        $absDir = Config::basePath('public' . $relDir);
        if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
        }

        // Rastgele ad: tahmin edilemez, çakışmaz.
        $base = bin2hex(random_bytes(16));
        $fileName = $base . '.' . $canonical;
        $absFile = $absDir . '/' . $fileName;

        if (!$this->encode($image, $canonical, $absFile)) {
            throw new RuntimeException('Görsel kaydedilemedi.');
        }
        chmod($absFile, 0644);

        if ($makeThumb) {
            $thumbFile = $absDir . '/' . $base . '_thumb.' . $canonical;
            $tw = min($thumbWidth, $w);
            $th = max(1, (int) round($h * $tw / $w));
            $thumb = $this->resize($image, $w, $h, $tw, $th, $hasAlpha);
            if (!$this->encode($thumb, $canonical, $thumbFile)) {
                @unlink($absFile);
                throw new RuntimeException('Küçük görsel kaydedilemedi.');
            }
            chmod($thumbFile, 0644);
        }

        return [
            'path'   => $relDir . '/' . $fileName,
            'width'  => $w,
            'height' => $h,
        ];
    }

    /**
     * Görseli ve küçük görselini siler. realpath ile uploads dışına çıkılamaz.
     */
    public function delete(string $path): void
    {
        if ($path === '' || !str_starts_with($path, '/uploads/')) {
            return;
        }

        $thumbPath = preg_replace('/\.(jpg|png|webp)$/', '_thumb.$1', $path);
        foreach ([$path, is_string($thumbPath) ? $thumbPath : ''] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $this->deleteOne($candidate);
        }
    }

    private function deleteOne(string $path): void
    {
        $root = realpath(Config::basePath('public/uploads'));
        $target = realpath(Config::basePath('public') . $path);
        if ($root === false || $target === false) {
            return;
        }
        // Gezinti saldırısı koruması: hedef uploads kökünün altında olmalı.
        if (!str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            return;
        }
        $ext = strtolower((string) pathinfo($target, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$ext]) || !is_file($target)) {
            return;
        }
        if (!@unlink($target)) {
            Logger::warning('Görsel silinemedi.', ['path' => $path]);
        }
    }

    /** EXIF yönünü uygular (yeniden encode sonrası EXIF kaybolacağı için). */
    private function applyExifOrientation(GdImage $image, string $tmp): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        // Bozuk EXIF uyarı üretebilir; sonuç yalnızca yön için okunur.
        $exif = @exif_read_data($tmp);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function resize(GdImage $src, int $w, int $h, int $newW, int $newH, bool $alpha): GdImage
    {
        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            throw new RuntimeException('Görsel belleği ayrılamadı.');
        }
        if ($alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, (int) $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        return $dst;
    }

    private function encode(GdImage $image, string $canonical, string $file): bool
    {
        if ($canonical === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return match ($canonical) {
            'jpg'   => imagejpeg($image, $file, 85),
            'png'   => imagepng($image, $file, 6),
            'webp'  => imagewebp($image, $file, 85),
            default => false,
        };
    }

    /**
     * Reddi güvenlik olayı olarak kaydeder ve 422 doğrulama hatası fırlatır.
     * Orijinal dosya adı loga yazılmaz.
     *
     * @param array<string,mixed> $ctx
     */
    private function reject(string $reason, string $message, array $ctx = []): never
    {
        SecurityEvent::record(
            'upload_rejected',
            'warning',
            Auth::id(),
            Request::capture()->ip(),
            ['reason' => $reason] + $ctx
        );

        throw new ValidationException(['file' => $message]);
    }
}