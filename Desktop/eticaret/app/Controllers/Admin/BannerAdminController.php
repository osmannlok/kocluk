<?php
// app/Controllers/Admin/BannerAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Banner;
use App\Services\ImageUploadService;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Banner yönetimi (banner.manage).
 * Güvenlik: her metot require() ile korunur; görsel işlemleri yalnızca ImageUploadService'te yapılır
 * (controller dosya sistemine dokunmaz); link_url beyaz liste şemasıyla doğrulanır (javascript:/data: reddedilir);
 * silme soft delete'tir ve her işlem audit_logs'a yazılır.
 */
final class BannerAdminController extends AdminBaseController
{
    /** Konum => Türkçe etiket (banners.position ENUM beyaz listesi). */
    private const POSITIONS = [
        'home_hero' => 'Ana sayfa vitrin (hero)',
        'home_secondary' => 'Ana sayfa ikincil',
        'category_top' => 'Kategori üstü',
    ];

    /** Yükleme alanı => boyut ve oran bilgisi (oran uyarısı bilgilendiricidir, engelleyici değildir). */
    private const IMAGE_SPECS = [
        'image_desktop' => ['label' => 'Masaüstü', 'max_width' => 1920, 'width' => 1920, 'height' => 700],
        'image_mobile' => ['label' => 'Mobil', 'max_width' => 900, 'width' => 900, 'height' => 1100],
    ];

    private const RATIO_TOLERANCE = 0.15;

    private function banners(): Banner
    {
        return new Banner();
    }

    private function images(): ImageUploadService
    {
        return new ImageUploadService();
    }

    public function index(Request $request): Response
    {
        $this->require('banner.manage');

        $result = $this->banners()->adminList(1, 100);

        $groups = [];
        foreach (array_keys(self::POSITIONS) as $position) {
            $groups[$position] = [];
        }
        foreach ($result['items'] as $row) {
            $position = (string) ($row['position'] ?? '');
            if (isset($groups[$position])) {
                $groups[$position][] = $row;
            }
        }
        foreach ($groups as $position => $rows) {
            usort($rows, static function (array $a, array $b): int {
                return [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']];
            });
            $groups[$position] = $rows;
        }

        return $this->view('admin.banners.index', [
            'title' => 'Bannerlar',
            'groups' => $groups,
            'positions' => self::POSITIONS,
            'now' => now(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->require('banner.manage');

        return $this->view('admin.banners.form', [
            'title' => 'Yeni banner',
            'banner' => [
                'title' => '', 'subtitle' => '', 'button_text' => '', 'link_url' => '',
                'position' => 'home_hero', 'alt_text' => '', 'starts_at' => '', 'ends_at' => '',
                'is_active' => 1, 'image_desktop' => null, 'image_mobile' => null,
            ],
            'isEdit' => false,
            'positions' => self::POSITIONS,
            'formAction' => url('/yonetim/bannerlar'),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->require('banner.manage');

        $data = $this->collect($request, null);

        // Önce görseller yüklenir; DB yazımı başarısız olursa yeni dosyalar silinir.
        $up = $this->uploadImages($request);
        $data['image_desktop'] = $up['image_desktop'];
        $data['image_mobile'] = $up['image_mobile'];
        $data['sort_order'] = $this->nextSortOrder((string) $data['position']);
        $data['created_by'] = Auth::id();

        try {
            $id = $this->banners()->create($data);
        } catch (Throwable $e) {
            $this->discard($up['paths']);
            throw $e;
        }

        $this->audit('banner.create', 'banner', $id, null, $this->snapshot($data));
        $this->flash('success', 'Banner oluşturuldu.');
        foreach ($up['warnings'] as $warning) {
            $this->flash('warning', $warning);
        }

        return $this->redirect(url('/yonetim/bannerlar'));
    }

    public function edit(Request $request, int $id): Response
    {
        $this->require('banner.manage');
        $banner = $this->findOrFail($id);

        return $this->view('admin.banners.form', [
            'title' => 'Banner düzenle',
            'banner' => $this->formValues($banner),
            'isEdit' => true,
            'positions' => self::POSITIONS,
            'formAction' => url('/yonetim/bannerlar/' . $id . '/guncelle'),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->require('banner.manage');
        $banner = $this->findOrFail($id);

        $data = $this->collect($request, $banner);
        $up = $this->uploadImages($request);

        // Eski dosyalar yalnızca DB yazımı başarılı olduktan sonra silinir.
        $replaced = [];
        if ($up['image_desktop'] !== null) {
            $data['image_desktop'] = $up['image_desktop'];
            $replaced[] = (string) $banner['image_desktop'];
        }
        if ($up['image_mobile'] !== null) {
            $data['image_mobile'] = $up['image_mobile'];
            if (!empty($banner['image_mobile'])) {
                $replaced[] = (string) $banner['image_mobile'];
            }
        } elseif ($request->post('remove_mobile') === '1' && !empty($banner['image_mobile'])) {
            $data['image_mobile'] = null;
            $replaced[] = (string) $banner['image_mobile'];
        }
        if ((string) $data['position'] !== (string) $banner['position']) {
            $data['sort_order'] = $this->nextSortOrder((string) $data['position']);
        }

        try {
            $this->banners()->update($id, $data);
        } catch (Throwable $e) {
            $this->discard($up['paths']);
            throw $e;
        }

        $this->discard($replaced);
        $this->audit('banner.update', 'banner', $id, $this->snapshot($banner), $this->snapshot($data + $banner));
        $this->flash('success', 'Banner güncellendi.');
        foreach ($up['warnings'] as $warning) {
            $this->flash('warning', $warning);
        }

        return $this->redirect(url('/yonetim/bannerlar'));
    }

    public function toggle(Request $request, int $id): Response
    {
        $this->require('banner.manage');
        $banner = $this->findOrFail($id);

        $new = ((int) $banner['is_active']) === 1 ? 0 : 1;
        $this->banners()->update($id, ['is_active' => $new]);

        $this->audit('banner.toggle', 'banner', $id, ['is_active' => (int) $banner['is_active']], ['is_active' => $new]);
        $this->flash('success', $new === 1 ? 'Banner yayına alındı.' : 'Banner yayından kaldırıldı.');

        return $this->redirect(url('/yonetim/bannerlar'));
    }

    /** POST JSON: {"position":"home_hero","ids":[3,1,2]} -> {"ok":true,"data":{"count":3}} */
    public function reorder(Request $request): Response
    {
        $this->require('banner.manage');

        $payload = $request->json();
        $position = $payload['position'] ?? null;
        $rawIds = $payload['ids'] ?? null;

        if (!is_string($position) || !isset(self::POSITIONS[$position])) {
            return $this->fail('validation', 'Konum geçersiz.', 422, ['position' => 'Konum geçersiz.']);
        }
        if (!is_array($rawIds) || $rawIds === []) {
            return $this->fail('validation', 'Sıralama listesi boş.', 422, ['ids' => 'Sıralama listesi boş.']);
        }

        $ids = [];
        foreach ($rawIds as $raw) {
            if (is_int($raw)) {
                $value = $raw;
            } elseif (is_string($raw) && preg_match('/^\d{1,18}$/', $raw) === 1) {
                $value = (int) $raw;
            } else {
                return $this->fail('validation', 'Sıralama listesi geçersiz.', 422, ['ids' => 'Sıralama listesi geçersiz.']);
            }
            if ($value < 1 || in_array($value, $ids, true)) {
                return $this->fail('validation', 'Sıralama listesi geçersiz.', 422, ['ids' => 'Sıralama listesi geçersiz.']);
            }
            $ids[] = $value;
        }

        // Bütünlük: liste, o konumdaki tüm (silinmemiş) bannerları birebir içermeli; başka konumun kaydına dokunulamaz.
        $existing = [];
        foreach ($this->banners()->all(['position' => $position]) as $row) {
            $existing[(int) $row['id']] = true;
        }
        if (count($ids) !== count($existing)) {
            return $this->fail('validation', 'Liste güncel değil; sayfayı yenileyin.', 422, ['ids' => 'Liste güncel değil.']);
        }
        foreach ($ids as $bannerId) {
            if (!isset($existing[$bannerId])) {
                return $this->fail('validation', 'Liste güncel değil; sayfayı yenileyin.', 422, ['ids' => 'Liste güncel değil.']);
            }
        }

        $banners = $this->banners();
        Database::instance()->transaction(static function () use ($banners, $ids): void {
            foreach ($ids as $index => $bannerId) {
                $banners->update($bannerId, ['sort_order' => $index + 1]);
            }
        });

        $this->audit('banner.reorder', 'banner', null, null, ['position' => $position, 'ids' => $ids]);

        return $this->ok(['count' => count($ids)]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->require('banner.manage');
        $banner = $this->findOrFail($id);

        // Soft delete: görsel dosyaları silinmez (geri alma ve denetim için).
        $this->banners()->softDelete($id);

        $this->audit('banner.delete', 'banner', $id, $this->snapshot($banner), null);
        $this->flash('success', 'Banner silindi.');

        return $this->redirect(url('/yonetim/bannerlar'));
    }

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $banner = $this->banners()->find($id);
        if ($banner === null) {
            throw new HttpException(404, 'Banner bulunamadı.');
        }

        return $banner;
    }

    private function nextSortOrder(string $position): int
    {
        $max = 0;
        foreach ($this->banners()->all(['position' => $position]) as $row) {
            $max = max($max, (int) $row['sort_order']);
        }

        return $max + 1;
    }

    /**
     * Girdiyi doğrular; hata varsa ValidationException fırlatır (görsel dosyaları burada yüklenmez).
     * @param array<string,mixed>|null $existing
     * @return array<string,mixed>
     */
    private function collect(Request $request, ?array $existing): array
    {
        $errors = [];

        $title = $this->text($request, 'title', 150, 'Başlık', $errors, true);
        $subtitle = $this->text($request, 'subtitle', 255, 'Alt başlık', $errors);
        $buttonText = $this->text($request, 'button_text', 60, 'Düğme metni', $errors);
        $linkUrl = $this->text($request, 'link_url', 255, 'Bağlantı', $errors);
        $altText = $this->text($request, 'alt_text', 190, 'Alt metin', $errors);

        if ($linkUrl !== '' && !isset($errors['link_url']) && !$this->isSafeLink($linkUrl)) {
            $errors['link_url'] = 'Bağlantı yalnızca "/" ile başlayan site içi yol veya "https://" ile başlayan adres olabilir.';
        }

        $position = (string) $request->post('position', '');
        if (!isset(self::POSITIONS[$position])) {
            $errors['position'] = 'Konum geçersiz.';
            $position = '';
        }

        $startsRaw = $this->text($request, 'starts_at', 20, 'Başlangıç tarihi', $errors);
        $endsRaw = $this->text($request, 'ends_at', 20, 'Bitiş tarihi', $errors);
        $startsAt = null;
        $endsAt = null;
        if ($startsRaw !== '' && !isset($errors['starts_at'])) {
            $startsAt = $this->parseDateTime($startsRaw);
            if ($startsAt === null) {
                $errors['starts_at'] = 'Başlangıç tarihi geçersiz.';
            }
        }
        if ($endsRaw !== '' && !isset($errors['ends_at'])) {
            $endsAt = $this->parseDateTime($endsRaw);
            if ($endsAt === null) {
                $errors['ends_at'] = 'Bitiş tarihi geçersiz.';
            }
        }
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            $errors['ends_at'] = 'Bitiş tarihi başlangıç tarihinden sonra olmalı.';
        }

        // Yeni bannerda masaüstü görseli zorunludur; düzenlemede mevcut görsel korunur.
        if ($existing === null && !$this->hasUpload($request->file('image_desktop'))) {
            $errors['image_desktop'] = 'Masaüstü görseli zorunludur.';
        }

        $isActive = $request->post('is_active') === '1' ? 1 : 0;

        if ($errors !== []) {
            throw new ValidationException($errors, $this->oldValues($request));
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle !== '' ? $subtitle : null,
            'button_text' => $buttonText !== '' ? $buttonText : null,
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
            'alt_text' => $altText !== '' ? $altText : null,
            'position' => $position,
            'starts_at' => $startsAt?->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
            'is_active' => $isActive,
        ];
    }

    /**
     * Yüklenen görselleri ImageUploadService ile kaydeder. Hata olursa bu istekte yüklenenleri siler.
     * @return array{image_desktop:?string,image_mobile:?string,paths:list<string>,warnings:list<string>}
     */
    private function uploadImages(Request $request): array
    {
        $result = ['image_desktop' => null, 'image_mobile' => null, 'paths' => [], 'warnings' => []];
        $field = '';

        try {
            foreach (self::IMAGE_SPECS as $field => $spec) {
                $file = $request->file($field);
                if (!$this->hasUpload($file)) {
                    continue;
                }

                // Banner görselleri 1600 px varsayılanına değil kendi genişliğine göre işlenir; küçük görsel üretilmez.
                $info = $this->images()->store($file, 'banners', ['max_width' => $spec['max_width'], 'thumb' => false]);

                $result[$field] = $info['path'];
                $result['paths'][] = $info['path'];

                $warning = $this->ratioWarning($spec, (int) $info['width'], (int) $info['height']);
                if ($warning !== null) {
                    $result['warnings'][] = $warning;
                }
            }
        } catch (ValidationException $e) {
            // Servisin Türkçe mesajı kullanıcıya gösterilir.
            $this->discard($result['paths']);
            $message = $e->errors()['file'] ?? 'Görsel yüklenemedi.';
            throw new ValidationException(
                [$field => is_string($message) ? $message : 'Görsel yüklenemedi.'],
                $this->oldValues($request)
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            // İç ayrıntı kullanıcıya sızdırılmaz; yalnızca loga yazılır.
            $this->discard($result['paths']);
            Logger::error('Banner görseli yüklenemedi.', ['field' => $field, 'error' => $e->getMessage()]);
            throw new ValidationException(
                [$field => 'Görsel yüklenemedi. Lütfen tekrar deneyin.'],
                $this->oldValues($request)
            );
        }

        return $result;
    }

    /**
     * @param array{label:string,max_width:int,width:int,height:int} $spec
     */
    private function ratioWarning(array $spec, int $width, int $height): ?string
    {
        if ($width < 1 || $height < 1) {
            return null;
        }

        $target = $spec['width'] / $spec['height'];
        $deviation = abs(($width / $height) / $target - 1);
        if ($deviation <= self::RATIO_TOLERANCE) {
            return null;
        }

        return sprintf(
            '%s görseli %dx%d piksel; önerilen yaklaşık %dx%d (en-boy oranında %%%d sapma). Banner yine de kaydedildi, görünüm kırpılabilir.',
            $spec['label'],
            $width,
            $height,
            $spec['width'],
            $spec['height'],
            (int) round($deviation * 100)
        );
    }

    /** @param list<string> $paths */
    private function discard(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path !== '') {
                $this->images()->delete($path);
            }
        }
    }

    /** @param array<string,mixed>|null $file */
    private function hasUpload(?array $file): bool
    {
        return $file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /** Açık yönlendirme/XSS koruması: yalnızca "/yol" (// değil) veya https://. */
    private function isSafeLink(string $url): bool
    {
        // Boşluk, kontrol karakteri ve ters bölü ("/\evil.com" hilesi) reddedilir.
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return false;
        }
        if ($url[0] === '/') {
            return !str_starts_with($url, '//');
        }
        if (stripos($url, 'https://') === 0) {
            $parts = parse_url($url);

            return is_array($parts)
                && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
                && !empty($parts['host'])
                && !isset($parts['user'], $parts['pass']);
        }

        return false;
    }

    /** @param array<string,string> $errors */
    private function text(Request $request, string $key, int $max, string $label, array &$errors, bool $required = false): string
    {
        $value = $request->post($key, '');
        $value = is_string($value) ? trim($value) : '';

        if (!mb_check_encoding($value, 'UTF-8')) {
            $errors[$key] = $label . ' geçersiz karakter içeriyor.';
            return '';
        }

        $value = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        if ($required && $value === '') {
            $errors[$key] = $label . ' zorunludur.';
        } elseif (mb_strlen($value) > $max) {
            $errors[$key] = $label . ' en çok ' . $max . ' karakter olabilir.';
        }

        return $value;
    }

    private function parseDateTime(string $raw): ?DateTimeImmutable
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $dt = DateTimeImmutable::createFromFormat('!' . $format, $raw);
            $err = DateTimeImmutable::getLastErrors();
            if ($dt !== false && ($err === false || ($err['warning_count'] === 0 && $err['error_count'] === 0))) {
                return $dt;
            }
        }

        return null;
    }

    /**
     * Hata durumunda forma geri dönecek değerler (dosya alanları taşınamaz).
     * @return array<string,string>
     */
    private function oldValues(Request $request): array
    {
        $out = [];
        foreach (['title', 'subtitle', 'button_text', 'link_url', 'position', 'alt_text', 'starts_at', 'ends_at'] as $key) {
            $value = $request->post($key, '');
            $out[$key] = is_string($value) ? $value : '';
        }
        $out['is_active'] = $request->post('is_active') === '1' ? '1' : '0';

        return $out;
    }

    /**
     * Formda gösterilecek değerler (datetime-local biçimi).
     * @param array<string,mixed> $banner
     * @return array<string,mixed>
     */
    private function formValues(array $banner): array
    {
        foreach (['starts_at', 'ends_at'] as $key) {
            $raw = $banner[$key] ?? null;
            $banner[$key] = is_string($raw) && $raw !== ''
                ? str_replace(' ', 'T', substr($raw, 0, 16))
                : '';
        }
        foreach (['subtitle', 'button_text', 'link_url', 'alt_text'] as $key) {
            $banner[$key] = $banner[$key] ?? '';
        }

        return $banner;
    }

    /**
     * Denetim kaydı özeti.
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function snapshot(array $row): array
    {
        $keys = [
            'title', 'subtitle', 'button_text', 'link_url', 'position', 'sort_order',
            'starts_at', 'ends_at', 'is_active', 'image_desktop', 'image_mobile',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }
}