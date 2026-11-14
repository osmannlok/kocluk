<?php
// resources/views/admin/banners/form.php
/**
 * Girdi: $title, $banner (form değerleri), $isEdit, $positions (konum => etiket), $formAction.
 * Güvenlik: tüm çıktı e() ile; form POST + CSRF; satır içi betik/stil yok. Görsel doğrulaması ve işleme
 * yalnızca sunucuda (BannerAdminController + ImageUploadService); buradaki öznitelikler kullanım kolaylığıdır.
 * Canlı önizleme [data-image-input][data-preview-target] ile işaretlidir (istemci betiği ayrı dosyadır).
 *
 * @var array<string,mixed> $banner
 * @var bool $isEdit
 * @var array<string,string> $positions
 * @var string $formAction
 */
$err = static function (string $key): string {
    $value = errors($key);

    return is_string($value) ? $value : '';
};
$val = static fn (string $key): string => (string) old($key, (string) ($banner[$key] ?? ''));
$position = $val('position');
if (!isset($positions[$position])) {
    $position = 'home_hero';
}
$isActive = (string) old('is_active', (string) ((int) ($banner['is_active'] ?? 1))) === '1';
$desktopPath = (string) ($banner['image_desktop'] ?? '');
$mobilePath = (string) ($banner['image_mobile'] ?? '');
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <a href="<?= e(url('/yonetim/bannerlar')) ?>" class="btn btn-outline-secondary">Listeye dön</a>
</div>

<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="admin-card" novalidate data-dirty-guard>
    <?= csrf_field() ?>
    <div class="admin-card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="bannerTitle" class="form-label">Başlık <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" id="bannerTitle" name="title" value="<?= e($val('title')) ?>"
                       class="form-control<?= $err('title') !== '' ? ' is-invalid' : '' ?>" maxlength="150" required>
                <?php if ($err('title') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('title')) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label for="bannerSubtitle" class="form-label">Alt başlık</label>
                <input type="text" id="bannerSubtitle" name="subtitle" value="<?= e($val('subtitle')) ?>"
                       class="form-control<?= $err('subtitle') !== '' ? ' is-invalid' : '' ?>" maxlength="255">
                <?php if ($err('subtitle') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('subtitle')) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-4">
                <label for="bannerButtonText" class="form-label">Düğme metni</label>
                <input type="text" id="bannerButtonText" name="button_text" value="<?= e($val('button_text')) ?>"
                       class="form-control<?= $err('button_text') !== '' ? ' is-invalid' : '' ?>" maxlength="60">
                <?php if ($err('button_text') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('button_text')) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-5">
                <label for="bannerLink" class="form-label">Düğme bağlantısı</label>
                <input type="text" id="bannerLink" name="link_url" value="<?= e($val('link_url')) ?>"
                       class="form-control<?= $err('link_url') !== '' ? ' is-invalid' : '' ?>" maxlength="255"
                       inputmode="url" autocomplete="off" aria-describedby="bannerLinkHelp">
                <?php if ($err('link_url') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('link_url')) ?></div>
                <?php endif; ?>
                <div id="bannerLinkHelp" class="form-text">Site içi yol ("/urunler") veya "https://" ile başlayan adres. Başka şemalar kabul edilmez.</div>
            </div>

            <div class="col-12 col-md-3">
                <label for="bannerPosition" class="form-label">Konum <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="bannerPosition" name="position" class="form-select<?= $err('position') !== '' ? ' is-invalid' : '' ?>" required>
                    <?php foreach ($positions as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $position === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($err('position') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('position')) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-lg-7">
                <label for="bannerImageDesktop" class="form-label">
                    Masaüstü görseli<?php if (!$isEdit): ?> <span class="text-danger" aria-hidden="true">*</span><?php endif; ?>
                </label>
                <input type="file" id="bannerImageDesktop" name="image_desktop"
                       class="form-control<?= $err('image_desktop') !== '' ? ' is-invalid' : '' ?>"
                       accept="image/jpeg,image/png,image/webp" data-image-input data-preview-target="bannerPreviewDesktop"
                       aria-describedby="bannerDesktopHelp"<?= !$isEdit ? ' required' : '' ?>>
                <?php if ($err('image_desktop') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('image_desktop')) ?></div>
                <?php endif; ?>
                <div id="bannerDesktopHelp" class="form-text">JPG, PNG veya WEBP. Önerilen yaklaşık 1920x700 piksel. Oran sapması kaydı engellemez, yalnızca uyarı verir.<?= $isEdit ? ' Boş bırakırsanız mevcut görsel korunur.' : '' ?></div>
                <div class="mt-2">
                    <div class="small text-muted mb-1">Önizleme</div>
                    <div class="ratio ratio-21x9 bg-light border rounded overflow-hidden">
                        <img id="bannerPreviewDesktop" src="<?= $desktopPath !== '' ? e(url($desktopPath)) : '' ?>"
                             alt="Masaüstü görsel önizlemesi" class="object-fit-cover<?= $desktopPath === '' ? ' d-none' : '' ?>"
                             data-original-src="<?= $desktopPath !== '' ? e(url($desktopPath)) : '' ?>">
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <label for="bannerImageMobile" class="form-label">Mobil görseli</label>
                <input type="file" id="bannerImageMobile" name="image_mobile"
                       class="form-control<?= $err('image_mobile') !== '' ? ' is-invalid' : '' ?>"
                       accept="image/jpeg,image/png,image/webp" data-image-input data-preview-target="bannerPreviewMobile"
                       aria-describedby="bannerMobileHelp">
                <?php if ($err('image_mobile') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('image_mobile')) ?></div>
                <?php endif; ?>
                <div id="bannerMobileHelp" class="form-text">İsteğe bağlı. Önerilen yaklaşık 900x1100 piksel. Yoksa mobilde masaüstü görseli kullanılır.</div>
                <div class="mt-2">
                    <div class="small text-muted mb-1">Önizleme</div>
                    <div class="ratio ratio-1x1 bg-light border rounded overflow-hidden">
                        <img id="bannerPreviewMobile" src="<?= $mobilePath !== '' ? e(url($mobilePath)) : '' ?>"
                             alt="Mobil görsel önizlemesi" class="object-fit-cover<?= $mobilePath === '' ? ' d-none' : '' ?>"
                             data-original-src="<?= $mobilePath !== '' ? e(url($mobilePath)) : '' ?>">
                    </div>
                </div>
                <?php if ($isEdit && $mobilePath !== ''): ?>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="bannerRemoveMobile" name="remove_mobile" value="1">
                        <label class="form-check-label" for="bannerRemoveMobile">Mevcut mobil görseli kaldır</label>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-12">
                <label for="bannerAlt" class="form-label">Alt metin (erişilebilirlik)</label>
                <input type="text" id="bannerAlt" name="alt_text" value="<?= e($val('alt_text')) ?>"
                       class="form-control<?= $err('alt_text') !== '' ? ' is-invalid' : '' ?>" maxlength="190"
                       aria-describedby="bannerAltHelp">
                <?php if ($err('alt_text') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('alt_text')) ?></div>
                <?php endif; ?>
                <div id="bannerAltHelp" class="form-text">Görseli görmeyen ziyaretçiler için kısa açıklama.</div>
            </div>

            <div class="col-12 col-md-6">
                <label for="bannerStartsAt" class="form-label">Yayın başlangıcı</label>
                <input type="datetime-local" id="bannerStartsAt" name="starts_at" value="<?= e($val('starts_at')) ?>"
                       class="form-control<?= $err('starts_at') !== '' ? ' is-invalid' : '' ?>">
                <?php if ($err('starts_at') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('starts_at')) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label for="bannerEndsAt" class="form-label">Yayın bitişi</label>
                <input type="datetime-local" id="bannerEndsAt" name="ends_at" value="<?= e($val('ends_at')) ?>"
                       class="form-control<?= $err('ends_at') !== '' ? ' is-invalid' : '' ?>" aria-describedby="bannerDatesHelp">
                <?php if ($err('ends_at') !== ''): ?>
                    <div class="text-danger small mt-1" role="alert"><?= e($err('ends_at')) ?></div>
                <?php endif; ?>
                <div id="bannerDatesHelp" class="form-text">Tarihler boşsa banner sürekli yayındadır (aktifse). Bitiş, başlangıçtan sonra olmalı.</div>
            </div>

            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" role="switch" id="bannerActive" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                    <label class="form-check-label" for="bannerActive">Banner aktif</label>
                </div>
            </div>
        </div>
    </div>
    <div class="admin-card-footer d-flex flex-wrap gap-2 p-3 border-top">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Değişiklikleri kaydet' : 'Banner oluştur' ?></button>
        <a href="<?= e(url('/yonetim/bannerlar')) ?>" class="btn btn-link">Vazgeç</a>
    </div>
</form>