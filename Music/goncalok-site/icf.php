<?php
require_once __DIR__ . '/config.php';
// ============================================================
// GONCA LÖK — ICF Sertifikası (PHP)
// ============================================================
$activePage = 'icf';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="ICF (International Coaching Federation) sertifikası nedir, neden önemlidir? Gonca LÖK'ün ICF sertifikalı koçluk standartları hakkında bilgi alın.">
  <title>ICF Sertifikası | Gonca LÖK</title>

  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
  <link rel="canonical" href="https://www.goncalokofficial.com/icf.php">
  <meta property="og:title" content="ICF Sertifikası | Gonca LÖK">
  <meta property="og:description" content="ICF (International Coaching Federation) sertifikası nedir, neden önemlidir? Gonca LÖK'ün ICF sertifikalı koçluk standartları hakkında bilgi alın.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://www.goncalokofficial.com/icf.php">
  <meta property="og:site_name" content="Gonca LÖK">
  <meta property="og:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="ICF Sertifikası | Gonca LÖK">
  <meta name="twitter:description" content="ICF (International Coaching Federation) sertifikası nedir, neden önemlidir? Gonca LÖK'ün ICF sertifikalı koçluk standartları hakkında bilgi alın.">
  <meta name="twitter:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">

</head>
<body data-page="icf">
  <a class="skip-link" href="#icerik">İçeriğe geç</a>
<?php require_once __DIR__ . '/partials/header.php'; ?>
<?php require_once __DIR__ . '/partials/wa-sticky.php'; ?>

  <main id="icerik">

    <section class="hero hero-navy hero-md">
      <div class="hero-pattern"></div>
      <div class="container">
        <div class="hero-content" style="max-width:680px;padding:100px 0 64px;">
          <span class="hero-badge"><?php echo icon("graduation", 15); ?> Uluslararası Standart</span>
          <h1 style="color:var(--white);">ICF Sertifikası <span class="gold-word">Nedir?</span></h1>
          <p class="hero-sub" style="color:rgba(244,244,244,0.78);">Dünya genelinde koçluk mesleğinin en yüksek standartlarını belirleyen uluslararası kuruluş: International Coaching Federation.</p>
        </div>
      </div>
    </section>

    <section class="section bg-white">
      <div class="container">
        <div class="grid-2 align-start">
          <div>
            <span class="eyebrow">ICF Nedir?</span>
            <h2>International Coaching <span class="gold-word">Federation</span></h2>
            <div class="divider mt-8 mb-24"></div>
            <p class="mb-16">ICF (International Coaching Federation), 1995 yılında kurulan ve günümüzde dünya genelinde 160'tan fazla ülkede faaliyet gösteren en büyük ve en prestijli uluslararası koçluk kuruluşudur.</p>
            <p class="mb-16">ICF sertifikalı bir koç; etik kurallara bağlı, belirli eğitim saatlerini tamamlamış ve yetkinlik standartlarını karşılamış bir profesyoneldir. Bu sertifika, dünya genelinde tanınan bir kalite güvencesidir.</p>
            <p>Gonca LÖK, ICF standartlarına göre eğitim almış ve sertifika kazanmıştır. Bu sizin için ne anlama gelir? Koçluktan aldığınız hizmetin etik, profesyonel ve uluslararası standartlarda olduğu anlamına gelir.</p>
          </div>
          <div>
            <div class="card card-navy card-pad-lg mb-20">
              <div class="tc-icon-lg"><?php echo icon("globe", 48); ?></div>
              <h4 style="color:var(--white);">Dünya Genelinde Tanınırlık</h4>
              <p style="color:rgba(244,244,244,0.72);">ICF sertifikası, 160+ ülkede geçerli, uluslararası düzeyde saygınlığı olan bir yetkinlik belgesidir.</p>
            </div>
            <div class="card mb-20">
              <div class="card-icon"><?php echo icon("check-circle", 26); ?></div>
              <h4>Etik Standartlar</h4>
              <p>ICF koçları, gizlilik, saygı ve dürüstlük ilkelerine bağlı bir etik koda uymakla yükümlüdür.</p>
            </div>
            <div class="card">
              <div class="card-icon"><?php echo icon("file-text", 26); ?></div>
              <h4>Yetkinlik Çerçevesi</h4>
              <p>8 temel yetkinlik alanında (aktif dinleme, güçlü sorular, farkındalık vb.) belirli standartları karşılayan koçlar sertifika alabilir.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Seviyeler -->
    <section class="section bg-cream">
      <div class="container">
        <div class="section-head center">
          <span class="eyebrow">Sertifikasyon Seviyeleri</span>
          <h2>ICF <span class="gold-word">Sertifika Seviyeleri</span></h2>
          <div class="divider divider-center"></div>
        </div>
        <div class="grid-3 mt-32">
          <div class="card level-card">
            <div class="level-emoji tc-medal tc-bronze"><?php echo icon("star-fill", 30); ?></div>
            <div class="tag">Başlangıç</div>
            <h4>ACC</h4>
            <p>Associate Certified Coach. En az 60 saatlik ICF onaylı koçluk eğitimi ve 100 saatlik koçluk deneyimi gerektirir.</p>
          </div>
          <div class="card level-card featured">
            <div class="level-emoji tc-medal tc-silver"><?php echo icon("star-fill", 30); ?></div>
            <div class="tag">Orta Düzey</div>
            <h4>PCC</h4>
            <p>Professional Certified Coach. En az 125 saatlik eğitim ve 500 saatlik koçluk deneyimi gerektirir.</p>
          </div>
          <div class="card level-card">
            <div class="level-emoji tc-medal tc-gold"><?php echo icon("star-fill", 30); ?></div>
            <div class="tag">İleri Düzey</div>
            <h4>MCC</h4>
            <p>Master Certified Coach. En az 200 saatlik eğitim ve 2.500 saatlik koçluk deneyimi gerektirir.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Güven bölümü -->
    <section class="section-sm bg-navy">
      <div class="container text-center">
        <h2 style="color:var(--white);">ICF Sertifikalı Koçla <span class="gold-word">Güvenle</span> Çalışın</h2>
        <p style="color:rgba(244,244,244,0.72);max-width:520px;margin:16px auto 32px;">Gonca LÖK ile çalışırken, hizmetin uluslararası standartlara uygun, etik ve profesyonel olduğundan emin olabilirsiniz.</p>
        <a href="iletisim.php" class="btn btn-primary">Randevu Al</a>
      </div>
    </section>

  </main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
  <script src="assets/js/site.js?v=18"></script>
</body>
</html>
