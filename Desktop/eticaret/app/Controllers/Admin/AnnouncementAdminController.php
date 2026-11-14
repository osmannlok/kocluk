<?php
// app/Controllers/Admin/AnnouncementAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Announcement;
use DateTimeImmutable;

/**
 * Duyuru şeridi yönetimi (banner.manage; duyuru için ayrı permission kodu yoktur).
 * Güvenlik: her metot require() ile korunur; link_url beyaz liste şemasıyla doğrulanır
 * (javascript:/data:/http:// reddedilir); her işlem audit_logs'a yazılır.
 * Not: announcements tablosunda deleted_at yoktur; bu yüzden silme kalıcıdır (forceDelete),
 * silinen kaydın özeti denetim kaydında saklanır.
 */
final class AnnouncementAdminController extends AdminBaseController
{
    private function announcements(): Announcement
    {
        return new Announcement();
    }

    /** Liste + ekleme/düzenleme formu. Düzenleme: GET /yonetim/duyurular?duzenle={id}. */
    public function index(Request $request): Response
    {
        $this->require('banner.manage');

        $result = $this->announcements()->adminList($this->currentPage($request), $this->perPage());

        $editing = null;
        $editRaw = (string) $request->query('duzenle', '');
        if ($editRaw !== '') {
            if (preg_match('/^\d{1,18}$/', $editRaw) !== 1) {
                throw new HttpException(404, 'Duyuru bulunamadı.');
            }
            $editing = $this->formValues($this->findOrFail((int) $editRaw));
        }

        $isEdit = $editing !== null;

        return $this->view('admin.announcements.index', [
            'title' => 'Duyurular',
            'announcements' => $result['items'],
            'pager' => $this->pager($result, $request),
            'announcement' => $editing ?? [
                'message' => '', 'link_url' => '', 'sort_order' => '0',
                'starts_at' => '', 'ends_at' => '', 'is_active' => 1,
            ],
            'isEdit' => $isEdit,
            'formAction' => $isEdit
                ? url('/yonetim/duyurular/' . (int) $editing['id'] . '/guncelle')
                : url('/yonetim/duyurular'),
            'now' => now(),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->require('banner.manage');

        $data = $this->collect($request);
        $id = $this->announcements()->create($data);

        $this->audit('announcement.create', 'announcement', $id, null, $this->snapshot($data));
        $this->flash('success', 'Duyuru oluşturuldu.');

        return $this->redirect(url('/yonetim/duyurular'));
    }

    public function update(Request $request, int $id): Response
    {
        $this->require('banner.manage');
        $row = $this->findOrFail($id);

        $data = $this->collect($request);
        $this->announcements()->update($id, $data);

        $this->audit('announcement.update', 'announcement', $id, $this->snapshot($row), $this->snapshot($data));
        $this->flash('success', 'Duyuru güncellendi.');

        return $this->redirect(url('/yonetim/duyurular'));
    }

    public function toggle(Request $request, int $id): Response
    {
        $this->require('banner.manage');
        $row = $this->findOrFail($id);

        $new = ((int) $row['is_active']) === 1 ? 0 : 1;
        $this->announcements()->update($id, ['is_active' => $new]);

        $this->audit('announcement.toggle', 'announcement', $id, ['is_active' => (int) $row['is_active']], ['is_active' => $new]);
        $this->flash('success', $new === 1 ? 'Duyuru yayına alındı.' : 'Duyuru yayından kaldırıldı.');

        return $this->redirect(url('/yonetim/duyurular'));
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->require('banner.manage');
        $row = $this->findOrFail($id);

        $this->announcements()->forceDelete($id);

        $this->audit('announcement.delete', 'announcement', $id, $this->snapshot($row), null);
        $this->flash('success', 'Duyuru silindi.');

        return $this->redirect(url('/yonetim/duyurular'));
    }

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $row = $this->announcements()->find($id);
        if ($row === null) {
            throw new HttpException(404, 'Duyuru bulunamadı.');
        }

        return $row;
    }

    /**
     * Girdiyi doğrular; hata varsa ValidationException fırlatır.
     * @return array<string,mixed>
     */
    private function collect(Request $request): array
    {
        $errors = [];

        $message = $this->text($request, 'message', 255, 'Duyuru metni', $errors, true);
        $linkUrl = $this->text($request, 'link_url', 255, 'Bağlantı', $errors);

        if ($linkUrl !== '' && !isset($errors['link_url']) && !$this->isSafeLink($linkUrl)) {
            $errors['link_url'] = 'Bağlantı yalnızca "/" ile başlayan site içi yol veya "https://" ile başlayan adres olabilir.';
        }

        $sortRaw = $request->post('sort_order', '');
        $sortRaw = is_string($sortRaw) ? trim($sortRaw) : '';
        $sortOrder = 0;
        if ($sortRaw !== '') {
            if (preg_match('/^\d{1,4}$/', $sortRaw) !== 1) {
                $errors['sort_order'] = 'Sıra 0 ile 9999 arasında bir tam sayı olmalı.';
            } else {
                $sortOrder = (int) $sortRaw;
            }
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

        $isActive = $request->post('is_active') === '1' ? 1 : 0;

        if ($errors !== []) {
            throw new ValidationException($errors, [
                'message' => $message,
                'link_url' => $linkUrl,
                'sort_order' => $sortRaw,
                'starts_at' => $startsRaw,
                'ends_at' => $endsRaw,
                'is_active' => (string) $isActive,
            ]);
        }

        return [
            'message' => $message,
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
            'sort_order' => $sortOrder,
            'starts_at' => $startsAt?->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
            'is_active' => $isActive,
        ];
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
     * Formda gösterilecek değerler (datetime-local biçimi).
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function formValues(array $row): array
    {
        foreach (['starts_at', 'ends_at'] as $key) {
            $raw = $row[$key] ?? null;
            $row[$key] = is_string($raw) && $raw !== ''
                ? str_replace(' ', 'T', substr($raw, 0, 16))
                : '';
        }
        $row['link_url'] = $row['link_url'] ?? '';
        $row['sort_order'] = (string) ($row['sort_order'] ?? '0');

        return $row;
    }

    /**
     * Denetim kaydı özeti.
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function snapshot(array $row): array
    {
        $out = [];
        foreach (['message', 'link_url', 'sort_order', 'starts_at', 'ends_at', 'is_active'] as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }
}