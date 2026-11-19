<?php
require_once __DIR__ . '/config.php';
// ============================================================
// GONCA LÖK — İletişim (PHP)
// ============================================================
$activePage = 'iletisim';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Gonca LÖK ile iletişime geçin. Ücretsiz ön görüşme talep edin — telefon, e-posta veya WhatsApp üzerinden hemen ulaşın.">
  <title>İletişim | Gonca LÖK</title>

  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
  <link rel="canonical" href="https://www.goncalokofficial.com/iletisim.php">
  <meta property="og:title" content="İletişim | Gonca LÖK">
  <meta property="og:description" content="Gonca LÖK ile iletişime geçin. Ücretsiz ön görüşme talep edin — telefon, e-posta veya WhatsApp üzerinden hemen ulaşın.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://www.goncalokofficial.com/iletisim.php">
  <meta property="og:site_name" content="Gonca LÖK">
  <meta property="og:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="İletişim | Gonca LÖK">
  <meta name="twitter:description" content="Gonca LÖK ile iletişime geçin. Ücretsiz ön görüşme talep edin — telefon, e-posta veya WhatsApp üzerinden hemen ulaşın.">
  <meta name="twitter:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">

</head>
<body data-page="iletisim">
  <a class="skip-link" href="#icerik">İçeriğe geç</a>
<?php require_once __DIR__ . '/partials/header.php'; ?>
<?php require_once __DIR__ . '/partials/wa-sticky.php'; ?>

  <main id="icerik">

    <section class="hero hero-cream hero-sm">
      <div class="hero-pattern"></div>
      <div class="container">
        <div class="hero-content" style="max-width:640px;padding:100px 0 56px;">
          <span class="hero-badge"><?php echo icon("mail-open", 15); ?> İletişim</span>
          <h1>İlk Adımı <span class="gold-word">Bugün</span> Atın</h1>
          <p class="hero-sub">Ücretsiz ön görüşme talep edin. Hiçbir taahhüt gerektirmiyor — sadece tanışalım ve ihtiyaçlarınızı konuşalım.</p>
        </div>
      </div>
    </section>

    <section class="section bg-white">
      <div class="container">
        <div class="grid-2 align-start">
          <!-- Form -->
          <div>
            <span class="eyebrow">Randevu Formu</span>
            <h2>Bize <span class="gold-word">Ulaşın</span></h2>
            <div class="divider mt-8 mb-32"></div>
            <div class="ssl-badge mb-24">
              <span aria-hidden="true"><?php echo icon("lock", 14); ?></span>
              <span>Verileriniz SSL ile korunmaktadır. Bilgileriniz gizli tutulur.</span>
            </div>
            <div class="form-success" id="formSuccess" role="status">
              <div class="tc-icon-lg" aria-hidden="true"><?php echo icon("check-circle", 36); ?></div>
              <h4>Mesajınız Ulaştı!</h4>
              <p class="mt-8">En kısa sürede size dönüş yapacağım. Teşekkürler!</p>
            </div>
            <form class="contact-form" id="contactForm" novalidate>
              <div class="form-field" id="field-name">
                <label for="name">Adınız Soyadınız *</label>
                <input type="text" id="name" name="name" autocomplete="name" required placeholder="Adınız Soyadınız">
                <span class="form-error">Lütfen adınızı ve soyadınızı girin.</span>
              </div>
              <div class="form-field" id="field-phone">
                <label for="phone">Telefon Numaranız *</label>
                <input type="tel" id="phone" name="phone" autocomplete="tel" required placeholder="05XX XXX XX XX">
                <span class="form-error">Lütfen geçerli bir telefon numarası girin.</span>
              </div>
              <div class="form-field" id="field-email">
                <label for="email">E-posta Adresiniz *</label>
                <input type="email" id="email" name="email" autocomplete="email" required placeholder="ornek@email.com">
                <span class="form-error">Lütfen geçerli bir e-posta adresi girin.</span>
              </div>
              <div class="form-field">
                <label for="coaching-type">Hangi Koçluk Hizmeti?</label>
                <select id="coaching-type" name="coaching_type">
                  <option value="egitim" selected>Öğrenci Koçluğu</option>
                  <option value="kariyer">Kariyer Koçluğu</option>
                  <option value="bilgi">Henüz Bilmiyorum</option>
                </select>
              </div>
              <div class="form-field" id="field-appointment_time">
                <label>Randevu Tarihi ve Saati *</label>
                <input type="hidden" id="appointment_date" name="appointment_date" required>
                <input type="hidden" id="appointment_time" name="appointment_time" required>
                <div class="slot-table-head">
                  <span class="slot-table-hint">Haftalık tablodan müsait bir saate tıklayın. Dolu görünen saatler seçilemez.</span>
                  <span class="slot-selected" id="selectedSlotLabel">Seçilen saat: —</span>
                </div>
                <div class="week-nav">
                  <button type="button" class="btn btn-secondary" id="weekPrev">← Önceki Hafta</button>
                  <strong class="week-label" id="weekLabel">Hafta yükleniyor...</strong>
                  <button type="button" class="btn btn-secondary" id="weekToday">Bu Hafta</button>
                  <button type="button" class="btn btn-secondary" id="weekNext">Sonraki Hafta →</button>
                </div>
                <input type="hidden" id="week_anchor" value="<?php echo date('Y-m-d'); ?>" data-today="<?php echo date('Y-m-d'); ?>">

                <div class="card public-appointments-card" style="padding:0;overflow:hidden;">
                  <div class="public-table-wrap">
                    <table class="week-table" id="userWeekTable">
                      <thead id="userWeekTableHead">
                        <tr><th colspan="7" class="public-table-empty">Hafta yükleniyor...</th></tr>
                      </thead>
                      <tbody id="userWeekTableBody">
                        <tr><td colspan="7" class="public-table-empty">Hafta yükleniyor...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <p class="week-legend">
                  <span><i class="week-legend-dot available"></i> Müsait (seçilebilir)</span>
                  <span><i class="week-legend-dot booked"></i> Dolu</span>
                  <span><i class="week-legend-dot past"></i> Geçmiş</span>
                </p>
                <span class="form-error">Lütfen tablodan müsait bir saat seçin.</span>
              </div>
              <div class="slot-feedback" id="slotFeedback" role="status" aria-live="polite"></div>
              <div class="form-field" id="field-message">
                <label for="message">Mesajınız (İsteğe Bağlı)</label>
                <textarea id="message" name="message" rows="4" placeholder="Öğrenciniz veya kendinizle ilgili kısaca bilgi verebilirsiniz..."></textarea>
              </div>
              <div class="form-field" id="field-privacy">
                <div class="privacy-row">
                  <input type="checkbox" id="privacy" name="privacy" required>
                  <label for="privacy"><a href="gizlilik.php" target="_blank" rel="noopener">Gizlilik Politikası</a>'nı ve <a href="kvkk.php" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>'ni okudum, kabul ediyorum. *</label>
                </div>
                <span class="form-error">Devam etmek için gizlilik politikasını kabul etmeniz gerekmektedir.</span>
              </div>
              <button type="submit" class="btn btn-primary btn-full btn-lg">Randevu Talebini Gönder</button>
              <div class="submit-feedback" id="submitFeedback" role="alert" aria-live="polite"></div>
            </form>
            <div class="assurance-row">
              <span><b><?php echo icon("check", 13); ?></b> 24 saat içinde dönüş</span>
              <span><b><?php echo icon("check", 13); ?></b> İlk görüşme ücretsiz</span>
              <span><b><?php echo icon("check", 13); ?></b> Veliye açık bilgi</span>
            </div>
          </div>

          <!-- İletişim bilgileri -->
          <div>
            <span class="eyebrow">Doğrudan İletişim</span>
            <h2>Hızlı <span class="gold-word">Ulaşın</span></h2>
            <div class="divider mt-8 mb-24"></div>
            <div class="contact-info-item">
              <div class="ci-icon" aria-hidden="true"><?php echo icon("phone", 20); ?></div>
              <div><div class="ci-label">Telefon</div><a href="tel:+905436839902" class="ci-value">0543 683 99 02</a></div>
            </div>
            <div class="contact-info-item">
              <div class="ci-icon" aria-hidden="true"><?php echo icon("mail", 20); ?></div>
              <div><div class="ci-label">E-posta</div><a href="mailto:goncalokofficial@gmail.com" class="ci-value">goncalokofficial@gmail.com</a></div>
            </div>
            <div class="contact-info-item">
              <div class="ci-icon" aria-hidden="true"><?php echo icon("message", 20); ?></div>
              <div><div class="ci-label">WhatsApp</div><a href="https://wa.me/905436839902?text=Merhaba%20Gonca%20Han%C4%B1m%2C%20ko%C3%A7luk%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum." target="_blank" rel="noopener" class="ci-value">WhatsApp'tan Yaz</a></div>
            </div>
            <div class="contact-info-item">
              <div class="ci-icon" aria-hidden="true"><?php echo icon("camera", 20); ?></div>
              <div><div class="ci-label">Instagram</div><a href="https://www.instagram.com/goncalokofficial/" target="_blank" rel="noopener" class="ci-value">@goncalokofficial</a></div>
            </div>
            <div class="contact-info-item">
              <div class="ci-icon" aria-hidden="true"><?php echo icon("globe", 20); ?></div>
              <div><div class="ci-label">Görüşme Formatı</div><div class="ci-value">Online &amp; Yüz Yüze</div></div>
            </div>

            <div class="card card-navy card-pad-lg mt-32">
              <h4 style="color:var(--white);" class="mb-16">WhatsApp'tan Hızlı Ulaşın</h4>
              <p style="color:rgba(244,244,244,0.72);" class="mb-20">Mesajınızı doğrudan WhatsApp'tan iletebilirsiniz. En hızlı yanıt kanalı.</p>
              <a href="https://wa.me/905436839902?text=Merhaba%20Gonca%20Han%C4%B1m%2C%20%C3%BCcretsiz%20%C3%B6n%20g%C3%B6r%C3%BC%C5%9Fme%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum." target="_blank" rel="noopener" class="btn btn-wa btn-full"><?php echo icon("message", 18); ?> WhatsApp'tan Yaz</a>
            </div>

            <div class="card mt-20" style="padding:24px;background:rgba(192,178,131,0.1);border:1.5px solid rgba(192,178,131,0.3);">
              <p style="font-size:14px;color:var(--navy);"><strong><?php echo icon("sparkles", 15); ?> İpucu:</strong> İlk görüşme tamamen ücretsiz ve taahhütsüzdür. Görüşme sonunda sürece devam edip etmeyeceğinize rahatça karar verebilirsiniz.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
  <script src="assets/js/site.js?v=18"></script>
</body>
</html>
