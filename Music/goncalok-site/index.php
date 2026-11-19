<?php
require_once __DIR__ . '/config.php';
// ============================================================
// GONCA LÖK — Ana Sayfa (PHP)
// ============================================================
$activePage = 'home';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Gonca LÖK – ICF Sertifikalı Öğrenci Koçluğu ve Kariyer Koçu. Öğrencinin akademik başarısını ve özgüvenini güçlendir. Randevu ve ücretsiz ön görüşme için iletişime geç.">
  <title>Gonca LÖK | ICF Sertifikalı Öğrenci Koçluğu ve Kariyer Koçu</title>

  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
  <link rel="canonical" href="https://www.goncalokofficial.com/">
  <meta property="og:title" content="Gonca LÖK | ICF Sertifikalı Öğrenci Koçluğu ve Kariyer Koçu">
  <meta property="og:description" content="Gonca LÖK – ICF Sertifikalı Öğrenci Koçluğu ve Kariyer Koçu. Öğrencinin akademik başarısını ve özgüvenini güçlendir. Randevu ve ücretsiz ön görüşme için iletişime geç.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://www.goncalokofficial.com/">
  <meta property="og:site_name" content="Gonca LÖK">
  <meta property="og:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Gonca LÖK | ICF Sertifikalı Öğrenci Koçluğu ve Kariyer Koçu">
  <meta name="twitter:description" content="Gonca LÖK – ICF Sertifikalı Öğrenci Koçluğu ve Kariyer Koçu. Öğrencinin akademik başarısını ve özgüvenini güçlendir. Randevu ve ücretsiz ön görüşme için iletişime geç.">
  <meta name="twitter:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Gonca LÖK",
    "url": "https://www.goncalokofficial.com/",
    "logo": "https://www.goncalokofficial.com/assets/images/logo.png",
    "sameAs": [
      "https://www.instagram.com/goncalokofficial/"
    ]
  }
  </script>

</head>
<body data-page="home">
  <a class="skip-link" href="#icerik">İçeriğe geç</a>
<?php require_once __DIR__ . '/partials/header.php'; ?>
<?php require_once __DIR__ . '/partials/wa-sticky.php'; ?>

  <main id="icerik">

    <!-- Hero -->
    <section class="hero hero-cream">
      <div class="hero-pattern"></div>
      <div class="container">
        <div class="hero-layout">
          <div class="hero-content" style="padding:80px 0 64px;">
            <span class="hero-badge"><?php echo icon("graduation", 15); ?> ICF Sertifikalı Öğrenci Koçluğu</span>
            <h1>Öğrencinin <span class="gold-word">Potansiyelini</span> Açığa Çıkart, Akademik Başarıya Dönüştür</h1>
            <p class="hero-sub">ICF sertifikalı öğrenci koçu Gonca LÖK ile öğrencinin ders çalışma düzeni, hedef belirleme, sınav motivasyonu ve özgüvenini güçlendir.</p>
            <div class="btn-group mt-32">
              <a href="iletisim.php" class="btn btn-primary">Randevu Al</a>
              <a href="iletisim.php" class="btn btn-secondary">Ücretsiz Ön Görüşme Talep Et</a>
            </div>
            <div class="hero-stats">
              <div><span class="hero-stat-num">ICF</span><span class="hero-stat-lbl">Uluslararası Sertifika</span></div>
              <div><span class="hero-stat-num">Online</span><span class="hero-stat-lbl">&amp; Yüz Yüze Görüşme</span></div>
              <div><span class="hero-stat-num">1-1</span><span class="hero-stat-lbl">Bireysel Koçluk</span></div>
            </div>
          </div>
          <div class="hero-visual">
            <div class="hero-img-box">
              <img src="assets/images/gonca-lok-tanitim.jpg" alt="Gonca LÖK, ICF sertifikalı öğrenci koçu, masasında danışanıyla çalışırken" width="1200" height="1200">
            </div>
            <div class="hero-img-badge"><?php echo icon("target", 14); ?> Hedef Odaklı Koçluk</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Kanıt şeridi -->
    <section class="proof-strip" aria-label="Gonca LÖK hakkında">
      <div class="container">
        <ul class="proof-strip-list">
          <li><span aria-hidden="true"><?php echo icon("check", 13); ?></span> ICF Sertifikalı Koç</li>
          <li><span aria-hidden="true"><?php echo icon("check", 13); ?></span> Online &amp; Yüz Yüze</li>
          <li><span aria-hidden="true"><?php echo icon("check", 13); ?></span> Öğrenci &amp; Veli Odağı</li>
          <li><span aria-hidden="true"><?php echo icon("check", 13); ?></span> İlk Görüşme Ücretsiz</li>
        </ul>
      </div>
    </section>
    <!-- Problem / çözüm bandı -->
    <section class="problem-band section-sm" aria-labelledby="problem-baslik">
      <div class="container">
        <div class="problem-grid">
          <div class="problem-col pain">
            <span class="problem-label">Size tanıdık geliyor mu?</span>
            <h3>Çalışıyor ama bir türlü tutmuyor</h3>
            <ul class="problem-list">
              <li><?php echo icon('alert', 17); ?> Defter dolu ama verim yok</li>
              <li><?php echo icon('alert', 17); ?> Oturup çalışamıyor, erteledikçe suçluluk artıyor</li>
              <li><?php echo icon('alert', 17); ?> Sınav kaygısı uykuları kaçırıyor</li>
              <li><?php echo icon('alert', 17); ?> Veli-öğrenci arasında gerginlik büyüyor</li>
            </ul>
          </div>
          <div class="problem-col solution">
            <span class="problem-label">Burada nasıl çalışıyoruz</span>
            <h3>Önce düzen, sonra performans</h3>
            <ul class="problem-list">
              <li><?php echo icon('check', 17); ?> Kişiye özel, sürdürülebilir çalışma planı</li>
              <li><?php echo icon('check', 17); ?> Her hafta net hedef + görünür takip</li>
              <li><?php echo icon('check', 17); ?> Kaygı yönetimi ve motivasyon araçları</li>
              <li><?php echo icon('check', 17); ?> Veli ile şeffaf, güvenli iletişim</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <section class="section bg-white">
      <div class="container">
        <div class="section-head center">
          <span class="eyebrow">Öğrenci Koçluğu</span>
          <h2>Öğrencine <span class="gold-word">Neler</span> Sunar?</h2>
          <div class="divider divider-center"></div>
          <p class="mt-16">Koçluk süreci, öğrencinin iç kaynaklarını keşfetmesine ve akademik başarıya giden yolda güçlü adımlar atmasına destek olur.</p>
        </div>
        <div class="grid-3">
          <div class="card">
            <div class="card-icon"><?php echo icon("calendar-month", 26); ?></div>
            <h4>Ders Çalışma Düzeni</h4>
            <p>Öğrencinin kendi rutinini oluşturmasına ve sürdürmesine destek olur.</p>
          </div>
          <div class="card">
            <div class="card-icon"><?php echo icon("target", 26); ?></div>
            <h4>Hedef Belirleme</h4>
            <p>Akademik hedeflerini netleştirir, gerçekçi ve motive edici bir yol haritası çizer.</p>
          </div>
          <div class="card">
            <div class="card-icon"><?php echo icon("motivation", 26); ?></div>
            <h4>Sınav Motivasyonu</h4>
            <p>Sınav dönemlerinde motivasyonu canlı tutar, stresi yönetme becerisi kazandırır.</p>
          </div>
          <div class="card">
            <div class="card-icon"><?php echo icon("sparkles", 26); ?></div>
            <h4>Özgüven Geliştirme</h4>
            <p>Öğrencinin güçlü yönlerini fark etmesini ve kendine güvenmesini destekler.</p>
          </div>
          <div class="card">
            <div class="card-icon"><?php echo icon("handshake", 26); ?></div>
            <h4>Sorumluluk Alma</h4>
            <p>Öğrencinin kendi sürecinin sahibi olmasını sağlar.</p>
          </div>
          <div class="card">
            <div class="card-icon"><?php echo icon("users", 26); ?></div>
            <h4>Öğrenci-Veli Uyumu</h4>
            <p>Veli ve öğrenci arasında sağlıklı iletişimi destekler, sürece dahil eder.</p>
          </div>
        </div>
        <div class="text-center mt-48">
          <a href="egitim.php" class="btn btn-primary">Öğrenci Koçluğunu Keşfet</a>
        </div>
      </div>
    </section>

    <!-- Nasıl çalışır -->
    <section class="section bg-cream">
      <div class="container">
        <div class="grid-2 align-center">
          <div>
            <span class="eyebrow">Süreç</span>
            <h2>Koçluk <span class="gold-word">Nasıl</span> İşler?</h2>
            <div class="divider mt-8 mb-24"></div>
            <div class="process-steps">
              <div class="process-step">
                <div class="step-num">1</div>
                <div class="step-content"><h4>Ücretsiz Ön Görüşme</h4><p>Öğrencinin ve velinin ihtiyaçlarını dinler, süreci anlatırım. Karar vermek için hiçbir baskı yok.</p></div>
              </div>
              <div class="process-step">
                <div class="step-num">2</div>
                <div class="step-content"><h4>Hedef Belirleme</h4><p>Öğrencinin gerçek hedeflerini ve engellerini birlikte ortaya çıkarırız.</p></div>
              </div>
              <div class="process-step">
                <div class="step-num">3</div>
                <div class="step-content"><h4>Soru Odaklı Seanslar</h4><p>Direktif vermek yerine soru sorarım — öğrenci kendi cevabını bulur, bu çok daha kalıcı olur.</p></div>
              </div>
              <div class="process-step">
                <div class="step-num">4</div>
                <div class="step-content"><h4>Takip &amp; Değerlendirme</h4><p>İlerlemeyi birlikte gözden geçirir, gerektiğinde süreci uyarlarız.</p></div>
              </div>
            </div>
          </div>
          <div>
            <div class="icf-badge mb-24">
              <div class="icf-seal">ICF</div>
              <div>
                <div class="icf-text-label">Uluslararası Standart</div>
                <div class="icf-text-name">International Coaching Federation</div>
              </div>
            </div>
            <div class="card">
              <h4 class="mb-16">ICF Sertifikalı Koçluk Neden Farklı?</h4>
              <p>ICF (International Coaching Federation), dünya genelinde en yüksek etik ve yetkinlik standartlarını belirleyen uluslararası koçluk kuruluşudur. Gonca LÖK, bu standartlara göre eğitim almış ve sertifika almaya hak kazanmıştır.</p>
              <a href="icf.php" class="btn btn-secondary mt-16" style="font-size:13px;padding:10px 20px;">ICF Hakkında Bilgi Al</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Vaka kesitleri -->
    <section class="section bg-white" aria-labelledby="vaka-baslik">
      <div class="container">
        <div class="section-head">
          <span class="eyebrow">Süreç Kesiti</span>
          <h2 id="vaka-baslik">Gerçek süreçlerden <span class="gold-word">kesitler</span></h2>
          <div class="divider mt-8"></div>
          <p class="mt-16">Aşağıdakiler, tipik bir koçluk sürecinin nasıl ilerlediğini gösteren temsilî kesitlerdir; süre ve yöntem kişiye göre değişir.</p>
        </div>
        <div class="grid-3">
          <article class="vaka-card vaka-before">
            <span class="vaka-tag">Başlangıç</span>
            <h3>Dağınık çalışma, motivasyon düşük</h3>
            <p>“Her gün ders çalışıyorum” deniyor ama plan yok, düzen yok, sadece suçluluk var.</p>
            <ul class="vaka-list"><li>Kaynak dağınıklığı</li><li>2 saatlik kesintisiz odak yok</li><li>Sınav kaygısı yükseliyor</li></ul>
          </article>
          <article class="vaka-card vaka-process">
            <span class="vaka-tag">Süreç · 6–10 hafta</span>
            <h3>Önce ritim, sonra hız</h3>
            <p>Hedef haritası çıkar, haftalık plan kurulur, her seans sonu küçük kazanım kutlanır.</p>
            <ul class="vaka-list"><li>1. Hafta: tanışma &amp; ölçüm</li><li>2–4. Hafta: düzen &amp; ritim</li><li>5–10. Hafta: hız &amp; sınav stratejisi</li></ul>
          </article>
          <article class="vaka-card vaka-after">
            <span class="vaka-tag">Sonuç</span>
            <h3>Kendi ritmini bulan öğrenci</h3>
            <p>Kendi çalışma düzenini kuran, kaygıyı yöneten, hedefine doğru sakin ilerleyen öğrenci ve rahatlayan veli.</p>
            <ul class="vaka-list"><li>Düzenli odak saatleri</li><li>Net hedef haritası</li><li>Sakin ve özgüvenli çalışma</li></ul>
          </article>
        </div>
      </div>
    </section>

    <!-- Veli Portalı -->
    <section class="section bg-cream" aria-labelledby="veli-portal-baslik">
      <div class="container">
        <div class="grid-2 align-center">
          <div>
            <span class="eyebrow">Veli Portalı</span>
            <h2 id="veli-portal-baslik">Süreci <span class="gold-word">şeffaf</span> takip edin</h2>
            <div class="divider mt-8 mb-24"></div>
            <p>Veli portalı, öğrencinin gelişimini <strong>gerçekten ve izinli şekilde</strong> görmenizi sağlar. Koçun özel notları, öğrencinin iç dünyasına ait paylaşımlar ve size ait olmayan bilgiler portalınızda asla yer almaz.</p>
            <ul class="vp-check-list mt-20">
              <li><?php echo icon("check", 16); ?> Hedef ilerleme yüzdesi</li>
              <li><?php echo icon("check", 16); ?> Paylaşılan seans özetleri</li>
              <li><?php echo icon("check", 16); ?> Ödev/görev tamamlanma durumu</li>
              <li><?php echo icon("check", 16); ?> Akademik ilerleme (ders &amp; sınav)</li>
              <li><?php echo icon("check", 16); ?> Size özel, 30 günlük güvenli bağlantı</li>
            </ul>
            <div class="btn-group mt-24">
              <a href="iletisim.php" class="btn btn-primary">Bilgi Alın</a>
              <a href="sss.php" class="btn btn-secondary">S.S.S. Beklentilerim</a>
            </div>
          </div>
          <div class="vp-mock" role="img" aria-label="Veli portalı temsilî görünümü: hedefler, seans özeti ve ödev durumu">
            <div class="vp-mock-bar"><span class="vp-mock-dot"></span><span class="vp-mock-dot"></span><span class="vp-mock-dot"></span><span class="vp-mock-url">portal.goncalok.com</span></div>
            <div class="vp-mock-body">
              <div class="vp-mock-head"><strong>Veli Portalı</strong><span class="vp-mock-badge">Size özel</span></div>
              <div class="vp-mock-card">
                <div class="vp-mock-card-head"><span>Hedef: YKS Matematik</span><b>%68</b></div>
                <div class="vp-mock-bar-track"><span style="width:68%"></span></div>
              </div>
              <div class="vp-mock-card">
                <div class="vp-mock-card-head"><span>Paylaşılan seans özeti · 12 Kasım</span></div>
                <p>“Tema: çalışma ritmi oturuyor. Bu hafta 2 deneme, 1 konu tekrarı.”</p>
              </div>
              <div class="vp-mock-card">
                <div class="vp-mock-card-head"><span>Ödevler</span><b>2 / 3</b></div>
                <div class="vp-mock-row-done"><?php echo icon("check", 13); ?> Geometri 40 soru</div>
                <div class="vp-mock-row-done"><?php echo icon("check", 13); ?> Paragraf 25 soru</div>
                <div class="vp-mock-row-open">○ Fonksiyon denemesi</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Yorumlar -->
    <section class="section-sm bg-cream" aria-labelledby="yorumlar-baslik">
      <div class="container">
        <div class="t-carousel-head">
          <div class="section-head" style="margin-bottom:0;">
            <span class="eyebrow">Deneyimler</span>
            <h2 id="yorumlar-baslik">Öğrenciler ve <span class="gold-word">veliler</span> ne diyor?</h2>
            <div class="divider mt-8"></div>
          </div>
          <div class="t-nav" aria-label="Yorumlar arasında gezin">
            <button type="button" class="t-nav-btn" id="tPrev" aria-label="Önceki yorumlar"><?php echo icon('chevron-left', 20); ?></button>
            <button type="button" class="t-nav-btn" id="tNext" aria-label="Sonraki yorumlar"><?php echo icon('chevron-right', 20); ?></button>
          </div>
        </div>
        <div class="t-carousel" role="region" aria-roledescription="carousel" aria-label="Veli ve öğrenci yorumları">
          <div class="t-carousel-track" id="tTrack" tabindex="0">
          <figure class="testimonial-card">
            <span class="tc-stars" role="img" aria-label="5 yıldız"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg></span>
            <blockquote>“Ders çalışma düzenini kendi kendine kuramıyordu; şimdi hafta içi 2 saatini gerçekten verimli geçiriyor.”</blockquote>
            <figcaption><span class="tc-avatar" aria-hidden="true">EK</span><span class="tc-meta">Velisi · <em>İstanbul</em></span></figcaption>
            <span class="tc-note">Örnek deneyim</span>
          </figure>
          <figure class="testimonial-card">
            <span class="tc-stars" role="img" aria-label="5 yıldız"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg></span>
            <blockquote>“YKS motivasyonum dibe vurmuştu; küçük hedefler ve haftalık ritimle geri döndüm.”</blockquote>
            <figcaption><span class="tc-avatar" aria-hidden="true">AD</span><span class="tc-meta">12. sınıf öğrencisi · <em>Ankara</em></span></figcaption>
            <span class="tc-note">Örnek deneyim</span>
          </figure>
          <figure class="testimonial-card">
            <span class="tc-stars" role="img" aria-label="5 yıldız"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg></span>
            <blockquote>“Veli olarak süreçte şeffaf kalmak çok değerliydi; kısa ve net geri bildirimler aldık.”</blockquote>
            <figcaption><span class="tc-avatar" aria-hidden="true">ST</span><span class="tc-meta">Velisi · <em>İstanbul</em></span></figcaption>
            <span class="tc-note">Örnek deneyim</span>
          </figure>
          <figure class="testimonial-card">
            <span class="tc-stars" role="img" aria-label="5 yıldız"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg></span>
            <blockquote>“Sınav kaygısını ‘düşman’ olmaktan çıkardık; planı ve kendi ritmini buldu.”</blockquote>
            <figcaption><span class="tc-avatar" aria-hidden="true">MB</span><span class="tc-meta">8. sınıf öğrencisi · <em>İzmir</em></span></figcaption>
            <span class="tc-note">Örnek deneyim</span>
          </figure>
          <figure class="testimonial-card">
            <span class="tc-stars" role="img" aria-label="5 yıldız"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg></span>
            <blockquote>“Kariyer hedefini netleştirdi; tercih döneminde yanında olduğumuzun farkında hissetti.”</blockquote>
            <figcaption><span class="tc-avatar" aria-hidden="true">KO</span><span class="tc-meta">12. sınıf öğrencisi · <em>Bursa</em></span></figcaption>
            <span class="tc-note">Örnek deneyim</span>
          </figure>
          <figure class="testimonial-card">
            <span class="tc-stars" role="img" aria-label="5 yıldız"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg></span>
            <blockquote>“Her hafta ‘neyi başardık?’ sorusuyla motive olduk; seanslarımız hedef odaklıydı.”</blockquote>
            <figcaption><span class="tc-avatar" aria-hidden="true">NA</span><span class="tc-meta">Velisi · <em>Eskişehir</em></span></figcaption>
            <span class="tc-note">Örnek deneyim</span>
          </figure>
          </div>
        </div>
        <p class="testimonial-disclaimer">Yorumlar, isimler KVKK kapsamında baş harflerle maskelenerek paylaşılır. Yukarıdaki alıntılar örnek deneyimi temsil eder.</p>
      </div>
    </section>

    <!-- Kariyer koçluğu ikincil -->
    <section class="section bg-white">
      <div class="container">
        <div class="kariyer-card">
          <span class="tag">İkincil Hizmet</span>
          <h3 class="mt-16">Kariyer Koçluğu</h3>
          <p style="max-width:560px;">Kariyerinde netlik yarat, güçlü yönlerini keşfet ve hedeflediğin role daha hızlı ulaş. Kariyer değişikliği, iş arama süreci veya kariyer planlaması için bireysel destek.</p>
          <div class="btn-group mt-24">
            <a href="kariyer.php" class="btn btn-primary">Kariyer Koçluğunu Keşfet</a>
            <a href="https://wa.me/905436839902" target="_blank" rel="noopener" class="btn btn-white">WhatsApp'tan Yaz</a>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA band -->
    <section class="section-sm bg-navy">
      <div class="container text-center">
        <h2 style="color:var(--white);">Çocuğunuzun <span class="gold-word">Potansiyelini</span> Açığa Çıkartın</h2>
        <p style="color:rgba(244,244,244,0.72);max-width:500px;margin:16px auto 32px;">İlk adım bir görüşme. Ücretsiz ön görüşmede öğrencinizin ihtiyaçlarını birlikte değerlendirelim.</p>
        <div class="btn-group" style="justify-content:center;">
          <a href="iletisim.php" class="btn btn-primary">Ücretsiz Ön Görüşme Talep Et</a>
          <a href="tel:+905436839902" class="btn btn-white"><?php echo icon("phone", 18); ?> Hemen Ara</a>
        </div>
      </div>
    </section>

  </main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
  <script src="assets/js/site.js?v=18"></script>
</body>
</html>
