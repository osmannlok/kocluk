<?php
/**
 * contact.php
 * Bahar Yöresel - İletişim Sayfası
 */

require_once __DIR__ . '/includes/header.php';   // session_start() ve $db burada hazırlanır
require_once __DIR__ . '/config/session.php';    // generateCSRFToken() burada tanımlı

// --- İLETİŞİM BİLGİLERİ (gerçek bilgilerle değiştir) ---
$iletisimAdres   = 'Cumhuriyet Mahallesi, 14 17. sokak No: 13 A1, Onikişubat / Kahramanmaraş'; // TODO
$iletisimTelefon = '0 (546) 744 67 22';                                                 // TODO
$iletisimEposta  = 'asiroglu.yoresel@gmail.com';                                             // TODO

// Telefon numarasını tel: linki için sadeleştir (rakamlar ve +)
$telefonLink = 'tel:' . preg_replace('/[^0-9+]/', '', $iletisimTelefon);

// --- FLASH MESAJLAR (contact_send.php tarafından session'a yazılır) ---
$basariMesaji = $_SESSION['contact_success'] ?? '';
$hataMesaji   = $_SESSION['contact_error'] ?? '';
$eskiDegerler = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_success'], $_SESSION['contact_error'], $_SESSION['contact_old']);

/**
 * Formu hata sonrası doldurmak için eski değeri güvenli şekilde döndürür.
 */
function eskiDeger(array $eski, string $alan): string
{
    return htmlspecialchars((string) ($eski[$alan] ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!-- SAYFA BAŞLIĞI -->
<section class="bg-success text-white text-center py-5">
    <div class="container py-3">
        <h1 class="display-6 fw-bold mb-2">İletişim</h1>
        <p class="lead mb-0" style="opacity: .95;">
            Sorularınız, siparişleriniz ve önerileriniz için bize ulaşın.
        </p>
    </div>
</section>
<!-- /SAYFA BAŞLIĞI -->

<div class="container py-5">

    <div class="row g-5">

        <!-- İLETİŞİM FORMU -->
        <div class="col-12 col-lg-7">
            <h2 class="h4 fw-bold mb-4">Bize Yazın</h2>

            <?php if ($basariMesaji !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    <?php echo htmlspecialchars($basariMesaji, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($hataMesaji !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?php echo htmlspecialchars($hataMesaji, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="/contact_send.php" method="POST" class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <!-- Bot tuzağı (honeypot): gerçek kullanıcılar görmez, botlar doldurur -->
                    <div style="position: absolute; left: -9999px;" aria-hidden="true">
                        <label for="website">Web sitesi</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="name" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?php echo eskiDeger($eskiDegerler, 'name'); ?>"
                                   placeholder="Adınız Soyadınız" maxlength="100" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo eskiDeger($eskiDegerler, 'email'); ?>"
                                   placeholder="ornek@eposta.com" maxlength="150" required>
                        </div>

                        <div class="col-12">
                            <label for="subject" class="form-label">Konu <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject"
                                   value="<?php echo eskiDeger($eskiDegerler, 'subject'); ?>"
                                   placeholder="Mesajınızın konusu" maxlength="200" required>
                        </div>

                        <div class="col-12">
                            <label for="message" class="form-label">Mesajınız <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="6"
                                      placeholder="Mesajınızı buraya yazın..." maxlength="3000"
                                      required><?php echo eskiDeger($eskiDegerler, 'message'); ?></textarea>
                        </div>

                        <div class="col-12 d-grid d-sm-flex justify-content-sm-end">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-send me-1"></i> Mesajı Gönder
                            </button>
                        </div>

                    </div>
                </div>
            </form>
        </div>
        <!-- /İLETİŞİM FORMU -->

        <!-- İLETİŞİM BİLGİLERİ -->
        <div class="col-12 col-lg-5">
            <h2 class="h4 fw-bold mb-4">İletişim Bilgileri</h2>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body d-flex align-items-start">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success flex-shrink-0 me-3"
                         style="width: 48px; height: 48px;">
                        <i class="bi bi-geo-alt fs-5"></i>
                    </div>
                    <div>
                        <h3 class="h6 fw-bold mb-1">Adres</h3>
                        <p class="text-secondary mb-0"><?php echo htmlspecialchars($iletisimAdres, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body d-flex align-items-start">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success flex-shrink-0 me-3"
                         style="width: 48px; height: 48px;">
                        <i class="bi bi-telephone fs-5"></i>
                    </div>
                    <div>
                        <h3 class="h6 fw-bold mb-1">Telefon</h3>
                        <a href="<?php echo htmlspecialchars($telefonLink, ENT_QUOTES, 'UTF-8'); ?>"
                           class="text-secondary text-decoration-none">
                            <?php echo htmlspecialchars($iletisimTelefon, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-start">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success flex-shrink-0 me-3"
                         style="width: 48px; height: 48px;">
                        <i class="bi bi-envelope fs-5"></i>
                    </div>
                    <div>
                        <h3 class="h6 fw-bold mb-1">E-posta</h3>
                        <a href="mailto:<?php echo htmlspecialchars($iletisimEposta, ENT_QUOTES, 'UTF-8'); ?>"
                           class="text-secondary text-decoration-none">
                            <?php echo htmlspecialchars($iletisimEposta, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <!-- /İLETİŞİM BİLGİLERİ -->

    </div>

    <!-- GOOGLE HARİTA -->
    <div class="mt-5">
        <h2 class="h4 fw-bold mb-4">Bizi Haritada Bulun</h2>
        <div class="ratio ratio-21x9 rounded-4 overflow-hidden shadow-sm" style="min-height: 320px;">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3161.244402362966!2d36.85238727543328!3d37.59640662241538!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x463bcc3b83210129%3A0x5d129fd9b808946c!2zQcWfaXJvxJ9sdSBZw7ZyZXNlbCB2ZSBZdW11cnRh!5e0!3m2!1str!2str!4v1791659545278!5m2!1str!2str" 
                title="Bahar Yöresel konum haritası (Kahramanmaraş)"
                style="border: 0;"
                allowfullscreen
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
    <!-- /GOOGLE HARİTA -->

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>