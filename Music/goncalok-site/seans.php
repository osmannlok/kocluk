<?php
require_once __DIR__ . '/config.php';
// ============================================================
// GONCA LÖK — Koçluk Seansı (PHP)
// ============================================================
$activePage = 'seans';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Koçluk seansı nasıl işler? Ücretsiz ön görüşmeden eylem planına kadar adım adım koçluk sürecini keşfedin.">
  <title>Koçluk Seansı Nasıl İşler? | Gonca LÖK</title>

  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
  <link rel="canonical" href="https://www.goncalokofficial.com/seans.php">
  <meta property="og:title" content="Koçluk Seansı Nasıl İşler? | Gonca LÖK">
  <meta property="og:description" content="Koçluk seansı nasıl işler? Ücretsiz ön görüşmeden eylem planına kadar adım adım koçluk sürecini keşfedin.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://www.goncalokofficial.com/seans.php">
  <meta property="og:site_name" content="Gonca LÖK">
  <meta property="og:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Koçluk Seansı Nasıl İşler? | Gonca LÖK">
  <meta name="twitter:description" content="Koçluk seansı nasıl işler? Ücretsiz ön görüşmeden eylem planına kadar adım adım koçluk sürecini keşfedin.">
  <meta name="twitter:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">

</head>
<body data-page="seans">
  <a class="skip-link" href="#icerik">İçeriğe geç</a>
<?php require_once __DIR__ . '/partials/header.php'; ?>
<?php require_once __DIR__ . '/partials/wa-sticky.php'; ?>

  <main id="icerik">

    <section class="hero hero-cream hero-md">
      <div class="hero-pattern"></div>
      <div class="container">
        <div class="hero-content narrow" style="padding:100px 0 64px;">
          <span class="hero-badge"><?php echo icon("calendar-month", 15); ?> Seans Bilgisi</span>
          <h1>Koçluk Seansı <span class="gold-word">Nasıl İşler?</span></h1>
          <p class="hero-sub">Bir koçluk seansında neler olduğunu, nasıl ilerlediğimizi ve sonunda ne elde ettiğini adım adım anlattım.</p>
        </div>
      </div>
    </section>

    <section class="section bg-white">
      <div class="container">
        <div class="grid-2" style="gap:64px;">
          <div>
            <span class="eyebrow">Seans Akışı</span>
            <h2>Adım Adım <span class="gold-word">Koçluk Süreci</span></h2>
            <div class="divider mt-8 mb-24"></div>
            <div class="process-steps">
              <div class="process-step">
                <div class="step-num">1</div>
                <div class="step-content">
                  <h4>Ücretsiz Ön Görüşme</h4>
                  <p>İlk adım tamamen ücretsiz ve taahhütsüz. Birbirimizi tanırız, ihtiyaçlarını ve beklentilerini dinlerim, koçluk sürecini anlatırım. Herhangi bir yükümlülük yoktur.</p>
                </div>
              </div>
              <div class="process-step">
                <div class="step-num">2</div>
                <div class="step-content">
                  <h4>Hedef Belirleme</h4>
                  <p>Gerçek hedefleri, engelleri, kaynakları ve motivasyon kaynaklarını birlikte ortaya çıkarırız. Bu seans genellikle kişiye özel içgörüler sunar.</p>
                </div>
              </div>
              <div class="process-step">
                <div class="step-num">3</div>
                <div class="step-content">
                  <h4>Soru Odaklı Seanslar</h4>
                  <p>Her seans, direktif vermek yerine güçlü sorular sormak üzerine kuruludur. Cevabı sen bulursun — bu nedenle değişim kalıcı olur. Seanslar genellikle 45–60 dakika sürer.</p>
                </div>
              </div>
              <div class="process-step">
                <div class="step-num">4</div>
                <div class="step-content">
                  <h4>Eylem Planı</h4>
                  <p>Her seansın sonunda somut, ulaşılabilir ve anlamlı adımlar belirlenir. Bir sonraki seansta bu adımların değerlendirmesi yapılır.</p>
                </div>
              </div>
              <div class="process-step">
                <div class="step-num">5</div>
                <div class="step-content">
                  <h4>Takip &amp; Değerlendirme</h4>
                  <p>İlerleme periyodik olarak gözden geçirilir. Süreç, ihtiyaçlara göre esnek biçimde uyarlanır.</p>
                </div>
              </div>
            </div>
          </div>
          <div>
            <span class="eyebrow">Sonuçlar</span>
            <h2>Seanslardan <span class="gold-word">Ne Elde Edersin?</span></h2>
            <div class="divider mt-8 mb-24"></div>
            <div class="card mb-16">
              <div class="card-icon"><?php echo icon("sparkles", 26); ?></div>
              <h4>Netlik</h4>
              <p>Kafandaki karmaşa yerini net bir yol haritasına bırakır.</p>
            </div>
            <div class="card mb-16">
              <div class="card-icon"><?php echo icon("motivation", 26); ?></div>
              <h4>Motivasyon</h4>
              <p>İçten gelen, sürdürülebilir bir motivasyon kaynağı keşfedersin.</p>
            </div>
            <div class="card mb-16">
              <div class="card-icon"><?php echo icon("eye", 26); ?></div>
              <h4>Farkındalık</h4>
              <p>Kendini, güçlü yönlerini ve engellerini daha iyi tanırsın.</p>
            </div>
            <div class="card mb-32">
              <div class="card-icon"><?php echo icon("motivation", 26); ?></div>
              <h4>Harekete Geçme</h4>
              <p>Seans sonunda somut eylem adımları ile ayrılırsın.</p>
            </div>

            <div class="card card-navy" style="padding:28px;">
              <h4 style="color:var(--white);" class="mb-16">Pratik Bilgiler</h4>
              <ul style="display:flex;flex-direction:column;gap:10px;">
                <li style="color:rgba(244,244,244,0.78);font-size:14px;">⏱ Seans süresi: 45–60 dakika</li>
                <li style="color:rgba(244,244,244,0.78);font-size:14px;"><?php echo icon("globe", 15); ?> Online (Zoom/Meet) veya yüz yüze</li>
                <li style="color:rgba(244,244,244,0.78);font-size:14px;"><?php echo icon("calendar", 15); ?> Haftada bir veya iki haftada bir seans</li>
                <li style="color:rgba(244,244,244,0.78);font-size:14px;"><?php echo icon("clock", 15); ?> Minimum 4–6 seans önerilir</li>
                <li style="color:rgba(244,244,244,0.78);font-size:14px;"><?php echo icon("check-circle", 15); ?> İlk ön görüşme ücretsiz</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="text-center mt-48">
          <a href="iletisim.php" class="btn btn-primary">Ücretsiz Ön Görüşme Talep Et</a>
        </div>
      </div>
    </section>

  </main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
  <script src="assets/js/site.js?v=18"></script>
</body>
</html>
