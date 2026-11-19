<?php
require_once __DIR__ . '/config.php';
// ============================================================
// GONCA LÖK — Hakkımda (PHP)
// ============================================================
$activePage = 'hakkinda';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Gonca LÖK hakkında: ICF sertifikalı öğrenci ve kariyer koçu, koçluk felsefesi ve yaklaşımı hakkında bilgi alın.">
  <title>Hakkımda | Gonca LÖK</title>

  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
  <link rel="canonical" href="https://www.goncalokofficial.com/hakkinda.php">
  <meta property="og:title" content="Hakkımda | Gonca LÖK">
  <meta property="og:description" content="Gonca LÖK hakkında: ICF sertifikalı öğrenci ve kariyer koçu, koçluk felsefesi ve yaklaşımı hakkında bilgi alın.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://www.goncalokofficial.com/hakkinda.php">
  <meta property="og:site_name" content="Gonca LÖK">
  <meta property="og:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-portre.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Hakkımda | Gonca LÖK">
  <meta name="twitter:description" content="Gonca LÖK hakkında: ICF sertifikalı öğrenci ve kariyer koçu, koçluk felsefesi ve yaklaşımı hakkında bilgi alın.">
  <meta name="twitter:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-portre.jpg">

</head>
<body data-page="hakkinda">
  <a class="skip-link" href="#icerik">İçeriğe geç</a>
<?php require_once __DIR__ . '/partials/header.php'; ?>
<?php require_once __DIR__ . '/partials/wa-sticky.php'; ?>

  <main id="icerik">

    <section class="hero hero-cream hero-md">
      <div class="hero-pattern"></div>
      <div class="container">
        <div class="hero-content narrow" style="padding:100px 0 64px;">
          <span class="hero-badge"><?php echo icon("users", 15); ?> Hakkımda</span>
          <h1>Merhaba, ben <span class="gold-word">Gonca LÖK</span></h1>
          <p class="hero-sub">ICF sertifikalı kariyer ve öğrenci koçu. Öğrencilerin ve profesyonellerin içlerindeki potansiyeli bulmalarına yardım etmek için buradayım.</p>
        </div>
      </div>
    </section>

    <section class="section bg-white">
      <div class="container">
        <div class="grid-2 align-start">
          <div>
            <div class="hero-img-box portrait" style="max-width:400px;">
              <img src="assets/images/gonca-lok-portre.jpg" alt="Gonca LÖK, ICF sertifikalı öğrenci ve kariyer koçu" width="900" height="900">
            </div>
            <div class="icf-badge mt-24">
              <div class="icf-seal">ICF</div>
              <div>
                <div class="icf-text-label">Sertifikalı Koç</div>
                <div class="icf-text-name">International Coaching Federation</div>
              </div>
            </div>
          </div>
          <div>
            <span class="eyebrow">Biyografi</span>
            <h2>Kim <span class="gold-word">Olduğum</span></h2>
            <div class="divider mt-8 mb-24"></div>
            <p class="mb-20">Koçluk yolculuğuma, insanların kendi içlerindeki cevapları zaten taşıdığına dair derin bir inançla başladım. Benim rolüm o cevabı bulmalarına alan açmak — yön göstermek değil, yönü birlikte keşfetmek.</p>
            <p class="mb-20">Özellikle öğrenci koçluğu alanında öğrencilerle çalışmak bana çok anlam ifade ediyor. Bir öğrencinin "yapabileceğime inandım" dediği an, tüm seansların karşılığı oluyor.</p>
            <p>Kariyer koçluğu ile de profesyonellerin kariyerlerinde netlik yaratmalarına, güçlü yönlerini keşfetmelerine ve doğru adımı atmalarına destek veriyorum.</p>

            <div class="card card-quote mt-32">
              <p style="font-style:italic;font-family:'Playfair Display',serif;font-size:18px;color:var(--navy);">"Her öğrencinin içinde bir potansiyel var. Koçluğun gücü, o potansiyeli öğrencinin kendisinin keşfetmesini sağlamaktır."</p>
              <p class="mt-8" style="font-size:13px;font-weight:600;color:var(--gold);">— Gonca LÖK</p>
            </div>

            <div class="mt-32">
              <h3>Koçluk <span class="gold-word">Felsefem</span></h3>
              <div class="divider mt-8 mb-16"></div>
              <div class="process-steps">
                <div class="process-step">
                  <div class="step-num"><?php echo icon("sparkles", 24); ?></div>
                  <div class="step-content"><h4>Öğrenci Merkezlilik</h4><p>Her seans, öğrencinin kendi iç sesini duymasına yönelik tasarlanır.</p></div>
                </div>
                <div class="process-step">
                  <div class="step-num"><?php echo icon("key", 24); ?></div>
                  <div class="step-content"><h4>Potansiyel Odaklılık</h4><p>Sorunlara değil, güçlü yönlere ve olanaklara odaklanırım.</p></div>
                </div>
                <div class="process-step">
                  <div class="step-num"><?php echo icon("handshake", 24); ?></div>
                  <div class="step-content"><h4>Üçlü Uyum</h4><p>Öğrenci, veli ve öğretmenin aynı yönde çalışması süreci güçlendirir.</p></div>
                </div>
              </div>
            </div>
            <div class="btn-group mt-32">
              <a href="iletisim.php" class="btn btn-primary">Randevu Al</a>
              <a href="icf.php" class="btn btn-secondary">ICF Hakkında</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section-sm bg-cream" aria-labelledby="hakkinda-yorumlar">
      <div class="container">
        <div class="section-head center" style="margin-bottom:32px;">
          <span class="eyebrow">Referanslar</span>
          <h2 id="hakkinda-yorumlar">Peki süreç <span class="gold-word">neyi</span> değiştiriyor?</h2>
          <div class="divider divider-center"></div>
        </div>
        <div class="grid-2">
          <figure class="testimonial-card">
            <span class="tc-stars" role="img" aria-label="5 yıldız"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg></span>
            <blockquote>“Ders çalışma düzenini kendi kendine kuramıyordu; şimdi hafta içi 2 saatini gerçekten verimli geçiriyor.”</blockquote>
            <figcaption><span class="tc-avatar" aria-hidden="true">EK</span><span class="tc-meta">Velisi · <em>İstanbul</em></span></figcaption>
            <span class="tc-note">Örnek deneyim</span>
          </figure>
          <figure class="testimonial-card">
            <span class="tc-stars" role="img" aria-label="5 yıldız"><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg><svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.5-4.9 4.2 1.5 6.2-5.8-3.4-5.8 3.4 1.5-6.2L2.8 9.2l6.4-.5z" fill="currentColor" stroke="none"/></svg></span>
            <blockquote>“Sınav kaygısını ‘düşman’ olmaktan çıkardık; planı ve kendi ritmini buldu.”</blockquote>
            <figcaption><span class="tc-avatar" aria-hidden="true">MB</span><span class="tc-meta">8. sınıf öğrencisi · <em>İzmir</em></span></figcaption>
            <span class="tc-note">Örnek deneyim</span>
          </figure>
        </div>
      </div>
    </section>

  </main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
  <script src="assets/js/site.js?v=18"></script>
</body>
</html>
