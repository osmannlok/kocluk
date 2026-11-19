<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Gonca LÖK web sitesi ve randevu sisteminin gizlilik politikası: çerezler, veri güvenliği, üçüncü taraflar ve haklarınız.">
  <title>Gizlilik Politikası | Gonca LÖK</title>
  <link rel="canonical" href="https://www.goncalokofficial.com/gizlilik.php">

  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">

</head>
<body data-page="gizlilik">
  <a class="skip-link" href="#icerik">İçeriğe geç</a>
<?php $activePage = 'gizlilik'; require_once __DIR__ . '/partials/header.php'; ?>
<?php require_once __DIR__ . '/partials/wa-sticky.php'; ?>

  <main id="icerik">
    <section class="hero hero-cream hero-md">
      <div class="hero-pattern"></div>
      <div class="container">
        <div class="hero-content narrow" style="padding:100px 0 56px;">
          <span class="hero-badge"><?php echo icon("lock", 15); ?> Gizlilik</span>
          <h1>Gizlilik <span class="gold-word">Politikası</span></h1>
          <p class="hero-sub">Bu sitede hangi verilerin toplandığını, nasıl korunduğunu ve size düşen hakları anlatır.</p>
        </div>
      </div>
    </section>

    <section class="section bg-white">
      <div class="container" style="max-width:820px;">
        <div class="card card-pad-lg" style="padding:36px 32px;">
          <h2>Bu Sitede Toplanan Veriler</h2>
          <ul>
            <li><strong>Randevu talebi formu:</strong> ad soyad, telefon, e-posta, tercih ettiğiniz seans türü/saati ve mesajınız (yalnızca randevuyu planlamak için)</li>
            <li><strong>Bülten/iletişim (varsa):</strong> yalnızca açık rızanızla</li>
            <li><strong>Teknik veriler:</strong> IP adresi ve tarayıcı bilgisi, güvenlik ve hız sınırlama (rate limiting) amacıyla kısa süreli olarak</li>
          </ul>
          <p>Pazarlama amacıyla veri satılmaz, profil çıkarılmaz. Üçüncü taraf reklam/izleme pikseli kullanılmaz.</p>

          <h2 class="mt-24">Çerezler</h2>
          <p>Bu site yalnızca zorunlu teknik çerezler kullanır (oturum güvenliği). Reklam veya izleme çerezi kullanılmaz; bu nedenle çerez duvarı (banner) gerekmez.</p>

          <h2 class="mt-24">Güvenlik</h2>
          <p>Kişisel verileriniz şifreli bağlantı (HTTPS) üzerinden, yetkilendirme ve erişim kayıtlarıyla (kim, ne zaman, hangi kayda erişti) korunarak saklanır. Randevu ve portal sistemlerimiz seans içeriğini üçüncü taraflarla paylaşmaz.</p>

          <h2 class="mt-24">Üçüncü Taraf Hizmetler</h2>
          <ul>
            <li><strong>Google Fonts (artık kullanılmıyor):</strong> yazı tipleri sitemizin kendi sunucusundan sunulur; hiçbir Google kaynağına istek yapılmaz.</li>
            <li><strong>WhatsApp:</strong> WhatsApp bağlantılarını tıklamanız hâlinde Meta’nın gizlilik politikasına tabi olursunuz.</li>
            <li><strong>Instagram:</strong> Profilimize dışarıdan bağlantı veriyoruz; içerikleri bu politikanın kapsamı dışındadır.</li>
          </ul>

          <h2 class="mt-24">Haklarınız</h2>
          <p>Verilerinize erişme, düzeltme, silme ve itiraz haklarınız için <a href="mailto:goncalokofficial@gmail.com">goncalokofficial@gmail.com</a> adresine yazın. Detaylı bilgi için <a href="kvkk.php">KVKK Aydınlatma Metni</a>’ne bakın.</p>

          <p class="admin-muted mt-24" style="font-size:13px;">Son güncelleme: 18 Kasım 2026.</p>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
  <script src="assets/js/site.js?v=18"></script>
</body>
</html>
