<?php
// app/Controllers/Admin/PageAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Page;
use App\Services\ContentSanitizer;

/**
 * CMS sayfaları yönetimi (page.manage).
 * Yasal (system_key'li) sayfalar silinemez ve slug'ı değiştirilemez.
 */
final class PageAdminController extends AdminBaseController
{
    private const MAX_CONTENT = 200000;
    private const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private function pages(): Page
    {
        return new Page();
    }

    private function sanitizer(): ContentSanitizer
    {
        return new ContentSanitizer();
    }

    public function index(Request $request): Response
    {
        $this->require('page.manage');

        $status = (string) $request->query('status', '');
        $where = [];
        if ($status === 'active') {
            $where['is_active'] = 1;
        } elseif ($status === 'passive') {
            $where['is_active'] = 0;
        } else {
            $status = '';
        }

        $result = $this->pages()->paginate($this->currentPage($request), $this->perPage(), $where, 'id DESC');

        return $this->view('admin.pages.index', [
            'title' => 'Sayfalar',
            'pages' => $result['items'],
            'pager' => $this->pager($result, $request),
            'status' => $status,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->require('page.manage');

        return $this->view('admin.pages.form', [
            'title' => 'Yeni sayfa',
            'page' => [
                'title' => '', 'slug' => '', 'content' => '', 'meta_title' => '',
                'meta_description' => '', 'is_active' => 1, 'show_in_footer' => 0, 'system_key' => null,
            ],
            'isEdit' => false,
            'isSystem' => false,
            'formAction' => url('/yonetim/sayfalar'),
            'pageScripts' => ['js/admin-page-editor.js'],
        ]);
    }

    public function store(Request $request): Response
    {
        $this->require('page.manage');

        $data = $this->collect($request, null);
        $id = $this->pages()->create($data);

        $this->audit('page.create', 'page', $id, null, $this->snapshot($data));
        $this->flash('success', 'Sayfa oluşturuldu.');

        return $this->redirect(url('/yonetim/sayfalar'));
    }

    public function edit(Request $request, int $id): Response
    {
        $this->require('page.manage');
        $page = $this->findOrFail($id);

        return $this->view('admin.pages.form', [
            'title' => 'Sayfayı düzenle',
            'page' => $page,
            'isEdit' => true,
            'isSystem' => !empty($page['system_key']),
            'formAction' => url('/yonetim/sayfalar/' . $id . '/guncelle'),
            'pageScripts' => ['js/admin-page-editor.js'],
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->require('page.manage');
        $page = $this->findOrFail($id);

        $data = $this->collect($request, $page);
        $this->pages()->update($id, $data);

        $this->audit('page.update', 'page', $id, $this->snapshot($page), $this->snapshot($data));
        $this->flash('success', 'Sayfa güncellendi.');

        return $this->redirect(url('/yonetim/sayfalar'));
    }

    public function toggle(Request $request, int $id): Response
    {
        $this->require('page.manage');
        $page = $this->findOrFail($id);

        $new = ((int) $page['is_active']) === 1 ? 0 : 1;
        $this->pages()->update($id, ['is_active' => $new]);

        $this->audit('page.toggle', 'page', $id, ['is_active' => (int) $page['is_active']], ['is_active' => $new]);
        $this->flash('success', $new === 1 ? 'Sayfa yayına alındı.' : 'Sayfa yayından kaldırıldı.');

        return $this->redirect(url('/yonetim/sayfalar'));
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->require('page.manage');
        $page = $this->findOrFail($id);

        // Yasal sayfalar (KVKK, mesafeli satış vb.) sistemin parçasıdır; silinemez.
        if (!empty($page['system_key'])) {
            $this->flash('danger', 'Yasal/sistem sayfaları silinemez.');
            return $this->redirect(url('/yonetim/sayfalar'));
        }

        $this->pages()->softDelete($id);
        $this->audit('page.delete', 'page', $id, $this->snapshot($page), null);
        $this->flash('success', 'Sayfa silindi.');

        return $this->redirect(url('/yonetim/sayfalar'));
    }

    /** POST JSON: {"content":"..."} -> temizlenmiş önizleme HTML'i. */
    public function preview(Request $request): Response
    {
        $this->require('page.manage');

        $payload = $request->json();
        $raw = $payload['content'] ?? '';
        $raw = is_string($raw) ? $raw : '';

        if (strlen($raw) > self::MAX_CONTENT) {
            return $this->fail('validation', 'İçerik çok uzun.', 422, ['content' => 'İçerik çok uzun.']);
        }

        // Önizleme de kaydetmeyle aynı temizleyiciden geçer; ham HTML istemciye dönmez.
        return $this->ok(['html' => $this->sanitizer()->clean($raw)]);
    }

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $page = $this->pages()->find($id);
        if ($page === null) {
            throw new HttpException(404, 'Sayfa bulunamadı.');
        }

        return $page;
    }

    /**
     * Girdiyi doğrular; hata varsa ValidationException fırlatır.
     * @param array<string,mixed>|null $existing
     * @return array<string,mixed>
     */
    private function collect(Request $request, ?array $existing): array
    {
        $errors = [];
        $isSystem = $existing !== null && !empty($existing['system_key']);

        $title = $this->text($request, 'title', 190, 'Başlık', $errors, true);

        if ($isSystem) {
            // Yasal sayfanın slug'ı sabittir; gelen değer yok sayılır.
            $slug = (string) $existing['slug'];
            $slugRaw = $slug;
        } else {
            $slugRaw = $this->text($request, 'slug', 160, 'Bağlantı adı', $errors);
            $slug = slugify($slugRaw !== '' ? $slugRaw : $title);
            if (!isset($errors['slug'])) {
                if ($slug === '' || preg_match(self::SLUG_PATTERN, $slug) !== 1 || strlen($slug) > 160) {
                    $errors['slug'] = 'Geçerli bir bağlantı adı girin (küçük harf, rakam ve tire).';
                } elseif ($this->pages()->slugExists($slug, $existing !== null ? (int) $existing['id'] : null)) {
                    $errors['slug'] = 'Bu bağlantı adı zaten kullanılıyor.';
                }
            }
        }

        $metaTitle = $this->text($request, 'meta_title', 160, 'SEO başlığı', $errors);
        $metaDescription = $this->text($request, 'meta_description', 255, 'SEO açıklaması', $errors);

        $rawContent = $request->post('content', '');
        $rawContent = is_string($rawContent) ? $rawContent : '';
        $content = '';
        if (strlen($rawContent) > self::MAX_CONTENT) {
            $errors['content'] = 'İçerik çok uzun.';
        } elseif (trim($rawContent) === '') {
            $errors['content'] = 'İçerik boş olamaz.';
        } elseif (!mb_check_encoding($rawContent, 'UTF-8')) {
            $errors['content'] = 'İçerikte geçersiz karakter var.';
        } else {
            // XSS savunması: kaydedilen HTML yalnızca temizleyiciden geçmiş olur.
            $content = $this->sanitizer()->clean($rawContent);
            if ($content === '') {
                $errors['content'] = 'İçerik temizlendikten sonra boş kaldı.';
            }
        }

        $isActive = $request->post('is_active') === '1' ? 1 : 0;
        $inFooter = $request->post('show_in_footer') === '1' ? 1 : 0;

        if ($errors !== []) {
            throw new ValidationException($errors, [
                'title' => $title,
                'slug' => $slugRaw,
                'content' => $rawContent,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'is_active' => (string) $isActive,
                'show_in_footer' => (string) $inFooter,
            ]);
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'is_active' => $isActive,
            'show_in_footer' => $inFooter,
            'meta_title' => $metaTitle !== '' ? $metaTitle : null,
            'meta_description' => $metaDescription !== '' ? $metaDescription : null,
        ];
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

    /**
     * Denetim kaydı için özet (tüm içerik loga yazılmaz, yalnızca uzunluğu).
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function snapshot(array $row): array
    {
        return [
            'title' => $row['title'] ?? null,
            'slug' => $row['slug'] ?? null,
            'is_active' => isset($row['is_active']) ? (int) $row['is_active'] : null,
            'show_in_footer' => isset($row['show_in_footer']) ? (int) $row['show_in_footer'] : null,
            'content_length' => strlen((string) ($row['content'] ?? '')),
        ];
    }
}