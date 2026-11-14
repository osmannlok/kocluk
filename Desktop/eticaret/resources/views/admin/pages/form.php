<?php
// resources/views/admin/pages/form.php
/**
 * @var array<string,mixed> $page
 * @var bool $isEdit
 * @var bool $isSystem
 * @var string $formAction
 */
$val = static fn (string $k, mixed $d = ''): mixed => old($k, $page[$k] ?? $d);
$err = static function (string $k): ?string {
    $m = errors($k);
    return is_string($m) && $m !== '' ? $m : null;
};
$isActive = (string) old('is_active', (string) ($page['is_active'] ?? 1)) === '1';
$inFooter = (string) old('show_in_footer', (string) ($page['show_in_footer'] ?? 0)) === '1';
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?= $isEdit ? 'Sayfayı düzenle' : 'Yeni sayfa' ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('/yonetim/sayfalar')) ?>">Listeye dön</a>
</div>

<?php if ($isSystem): ?>
    <div class="alert alert-warning" role="alert">
        Bu bir yasal/sistem sayfasıdır: silinemez ve bağlantı adı (slug) değiştirilemez. Metni hukuki danışmanınızla gözden geçirin.
    </div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" data-dirty-guard novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="admin-card mb-3">
                <div class="admin-card-body">
                    <div class="mb-3">
                        <label for="pageTitle" class="form-label">Başlık <span class="text-danger">*</span></label>
                        <input type="text" id="pageTitle" name="title" maxlength="190" required
                               class="form-control<?= $err('title') ? ' is-invalid' : '' ?>"
                               value="<?= e($val('title')) ?>">
                        <?php if ($err('title')): ?><div class="invalid-feedback"><?= e($err('title')) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="pageSlug" class="form-label">Bağlantı adı (slug)</label>
                        <div class="input-group">
                            <span class="input-group-text">/sayfa/</span>
                            <input type="text" id="pageSlug" name="slug" maxlength="160"
                                   class="form-control<?= $err('slug') ? ' is-invalid' : '' ?>"
                                   value="<?= e($val('slug')) ?>"
                                   <?= $isSystem ? 'readonly' : '' ?>
                                   aria-describedby="pageSlugHelp">
                            <?php if ($err('slug')): ?><div class="invalid-feedback"><?= e($err('slug')) ?></div><?php endif; ?>
                        </div>
                        <div id="pageSlugHelp" class="form-text">Boş bırakırsanız başlıktan üretilir.</div>
                    </div>

                    <div data-page-editor
                         data-preview-url="<?= e(url('/yonetim/sayfalar/onizle')) ?>"
                         data-csrf="<?= e(csrf_token()) ?>">
                        <label for="pageContent" class="form-label">İçerik <span class="text-danger">*</span></label>

                        <div class="btn-toolbar gap-1 mb-2" role="toolbar" aria-label="Biçimlendirme araçları">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="bold" title="Kalın" aria-label="Kalın"><i class="bi bi-type-bold" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="italic" title="İtalik" aria-label="İtalik"><i class="bi bi-type-italic" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="h2" title="Başlık (H2)" aria-label="Başlık H2">H2</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="h3" title="Alt başlık (H3)" aria-label="Alt başlık H3">H3</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="ul" title="Madde listesi" aria-label="Madde listesi"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="ol" title="Numaralı liste" aria-label="Numaralı liste"><i class="bi bi-list-ol" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="quote" title="Alıntı" aria-label="Alıntı"><i class="bi bi-quote" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="link" title="Bağlantı" aria-label="Bağlantı"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-primary ms-auto" data-editor-preview>Önizle</button>
                        </div>

                        <textarea id="pageContent" name="content" rows="16" data-editor-input
                                  class="form-control font-monospace<?= $err('content') ? ' is-invalid' : '' ?>"
                                  aria-describedby="pageContentHelp"><?= e($val('content')) ?></textarea>
                        <?php if ($err('content')): ?><div class="invalid-feedback d-block"><?= e($err('content')) ?></div><?php endif; ?>
                        <div id="pageContentHelp" class="form-text">
                            İzinli: paragraf, başlık (h2–h4), liste, kalın/italik, bağlantı, görsel (/uploads/ veya https), alıntı, basit tablo. Diğer her şey kaydedilirken temizlenir.
                        </div>

                        <div class="mt-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <h2 class="h6 mb-2">Önizleme</h2>
                                <span class="small text-muted" data-editor-status role="status" aria-live="polite"></span>
                            </div>
                            <div class="border rounded p-3 bg-body overflow-auto" data-editor-panel aria-label="Sayfa önizlemesi">
                                <span class="text-muted">Önizlemek için “Önizle” düğmesine basın.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-card mb-3">
                <div class="admin-card-header"><h2 class="h6 mb-0">Yayın</h2></div>
                <div class="admin-card-body">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="pageActive" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                        <label class="form-check-label" for="pageActive">Yayında</label>
                    </div>
                    <input type="hidden" name="show_in_footer" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="pageFooter" name="show_in_footer" value="1"<?= $inFooter ? ' checked' : '' ?>>
                        <label class="form-check-label" for="pageFooter">Footer’da göster</label>
                    </div>
                </div>
            </div>

            <div class="admin-card mb-3">
                <div class="admin-card-header"><h2 class="h6 mb-0">SEO</h2></div>
                <div class="admin-card-body">
                    <div class="mb-3">
                        <label for="pageMetaTitle" class="form-label">SEO başlığı</label>
                        <input type="text" id="pageMetaTitle" name="meta_title" maxlength="160"
                               class="form-control<?= $err('meta_title') ? ' is-invalid' : '' ?>"
                               value="<?= e($val('meta_title')) ?>">
                        <?php if ($err('meta_title')): ?><div class="invalid-feedback"><?= e($err('meta_title')) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label for="pageMetaDesc" class="form-label">SEO açıklaması</label>
                        <textarea id="pageMetaDesc" name="meta_description" rows="3" maxlength="255"
                                  class="form-control<?= $err('meta_description') ? ' is-invalid' : '' ?>"><?= e($val('meta_description')) ?></textarea>
                        <?php if ($err('meta_description')): ?><div class="invalid-feedback"><?= e($err('meta_description')) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </div>
    </div>
</form>