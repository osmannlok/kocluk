<?php
declare(strict_types=1);

// resources/views/front/contact.php
// İletişim sayfası. Girdi: $settings (publicAll). Hata/eski değerler errors()/old() ile gelir.

use App\Core\View;

$settings = isset($settings) && is_array($settings) ? $settings : [];
$user = auth();
$defaultName = $user ? trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? '')) : '';
$defaultEmail = $user ? (string) ($user['email'] ?? '') : '';

$phone = trim((string) ($settings['contact.phone'] ?? ''));
$phoneHref = preg_replace('/[^\d+]/', '', $phone) ?? '';
$email = trim((string) ($settings['contact.email'] ?? ''));
$address = trim((string) ($settings['contact.address'] ?? ''));
$whatsapp = preg_replace('/\D+/', '', (string) ($settings['contact.whatsapp'] ?? '')) ?? '';

// Alan hatası: errors(alan) string|array|null döner; ilk mesaj alınır.
$fieldError = static function (string $field): string {
    $err = errors($field);
    if (is_array($err)) {
        $err = reset($err);
    }
    return is_string($err) ? $err : '';
};
$errName = $fieldError('name');
$errEmail = $fieldError('email');
$errPhone = $fieldError('phone');
$errSubject = $fieldError('subject');
$errMessage = $fieldError('message');
?>
<div class="container">
<?= View::partial('partials.breadcrumb', ['crumbs' => [['Ana Sayfa', '/'], ['İletişim', null]]]) ?>

    <h1 class="h2 mb-3">İletişim</h1>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-7">
            <form class="ym-panel" method="post" action="<?= e(url('/iletisim')) ?>" novalidate>
                <?= csrf_field() ?>

                <!-- Honeypot: gerçek kullanıcıya görünmez (CSS ile gizli), botlar doldurur -->
                <div class="ym-hp" aria-hidden="true">
                    <label for="website">Web siteniz (boş bırakın)</label>
                    <input type="text" id="website" name="website" value="" tabindex="-1" autocomplete="off">
                </div>

                <div class="mb-3">
                    <label for="contactName" class="form-label">Ad soyad</label>
                    <input type="text" id="contactName" name="name" class="form-control<?= $errName !== '' ? ' is-invalid' : '' ?>" value="<?= e((string) old('name', $defaultName)) ?>" maxlength="120" required autocomplete="name"<?= $errName !== '' ? ' aria-invalid="true" aria-describedby="contactNameErr"' : '' ?>>
<?php if ($errName !== ''): ?>
                    <div id="contactNameErr" class="invalid-feedback d-block"><?= e($errName) ?></div>
<?php endif; ?>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="contactEmail" class="form-label">E-posta</label>
                        <input type="email" id="contactEmail" name="email" class="form-control<?= $errEmail !== '' ? ' is-invalid' : '' ?>" value="<?= e((string) old('email', $defaultEmail)) ?>" maxlength="190" required autocomplete="email" inputmode="email"<?= $errEmail !== '' ? ' aria-invalid="true" aria-describedby="contactEmailErr"' : '' ?>>
<?php if ($errEmail !== ''): ?>
                        <div id="contactEmailErr" class="invalid-feedback d-block"><?= e($errEmail) ?></div>
<?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="contactPhone" class="form-label">Telefon <span class="text-muted fw-normal">(isteğe bağlı)</span></label>
                        <input type="tel" id="contactPhone" name="phone" class="form-control<?= $errPhone !== '' ? ' is-invalid' : '' ?>" value="<?= e((string) old('phone', '')) ?>" maxlength="20" placeholder="05XX XXX XX XX" autocomplete="tel" inputmode="tel"<?= $errPhone !== '' ? ' aria-invalid="true" aria-describedby="contactPhoneErr"' : '' ?>>
<?php if ($errPhone !== ''): ?>
                        <div id="contactPhoneErr" class="invalid-feedback d-block"><?= e($errPhone) ?></div>
<?php endif; ?>
                    </div>
                </div>

                <div class="my-3">
                    <label for="contactSubject" class="form-label">Konu</label>
                    <input type="text" id="contactSubject" name="subject" class="form-control<?= $errSubject !== '' ? ' is-invalid' : '' ?>" value="<?= e((string) old('subject', '')) ?>" maxlength="190" required<?= $errSubject !== '' ? ' aria-invalid="true" aria-describedby="contactSubjectErr"' : '' ?>>
<?php if ($errSubject !== ''): ?>
                    <div id="contactSubjectErr" class="invalid-feedback d-block"><?= e($errSubject) ?></div>
<?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="contactMessage" class="form-label">Mesajınız</label>
                    <textarea id="contactMessage" name="message" class="form-control<?= $errMessage !== '' ? ' is-invalid' : '' ?>" rows="6" minlength="10" maxlength="2000" required aria-describedby="contactMessageHelp<?= $errMessage !== '' ? ' contactMessageErr' : '' ?>"<?= $errMessage !== '' ? ' aria-invalid="true"' : '' ?>><?= e((string) old('message', '')) ?></textarea>
                    <div id="contactMessageHelp" class="form-text">En az 10, en çok 2000 karakter.</div>
<?php if ($errMessage !== ''): ?>
                    <div id="contactMessageErr" class="invalid-feedback d-block"><?= e($errMessage) ?></div>
<?php endif; ?>
                </div>

                <p class="small text-muted">Bilgileriniz yalnızca talebinizi yanıtlamak için kullanılır. Ayrıntılar için <a href="<?= e(url('/sayfa/kvkk')) ?>">KVKK Aydınlatma Metni</a>'ni inceleyin.</p>

                <button type="submit" class="btn btn-primary btn-lg w-100 w-md-auto">Mesajı gönder</button>
            </form>
        </div>

        <div class="col-12 col-lg-5">
            <div class="ym-panel">
                <h2 class="h5">İletişim bilgileri</h2>
                <ul class="list-unstyled mb-0 mt-3">
<?php if ($phone !== '' && $phoneHref !== ''): ?>
                    <li class="d-flex gap-2 py-2"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-phone"></use></svg><span><span class="visually-hidden">Telefon: </span><a href="<?= e('tel:' . $phoneHref) ?>"><?= e($phone) ?></a></span></li>
<?php endif; ?>
<?php if ($email !== ''): ?>
                    <li class="d-flex gap-2 py-2"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-mail"></use></svg><span><span class="visually-hidden">E-posta: </span><a href="<?= e('mailto:' . $email) ?>"><?= e($email) ?></a></span></li>
<?php endif; ?>
<?php if ($whatsapp !== ''): ?>
                    <li class="d-flex gap-2 py-2"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-whatsapp"></use></svg><a href="<?= e('https://wa.me/' . $whatsapp) ?>" target="_blank" rel="noopener noreferrer">WhatsApp ile yazın</a></li>
<?php endif; ?>
<?php if ($address !== ''): ?>
                    <li class="d-flex gap-2 py-2"><svg class="ym-icon" aria-hidden="true" focusable="false"><use href="#i-pin"></use></svg><span><span class="visually-hidden">Adres: </span><?= e($address) ?></span></li>
<?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
