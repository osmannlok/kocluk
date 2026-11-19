<?php
// ============================================================
// GONCA LÖK — Header görünümü (PHP partial)
// ============================================================
require_once __DIR__ . '/../config.php';
$activePage = $activePage ?? 'home';
$navItems = [
    ['id' => 'home',      'href' => 'index.php',     'label' => 'Ana Sayfa',           'icon' => 'home'],
    ['id' => 'egitim',    'href' => 'egitim.php',    'label' => 'Öğrenci Koçluğu',      'icon' => 'book'],
    ['id' => 'kariyer',   'href' => 'kariyer.php',   'label' => 'Kariyer Koçluğu',     'icon' => 'briefcase'],
    ['id' => 'hakkinda',  'href' => 'hakkinda.php',  'label' => 'Hakkında',            'icon' => 'users'],
    ['id' => 'icf',       'href' => 'icf.php',       'label' => 'ICF',                 'icon' => 'graduation'],
    ['id' => 'seans',     'href' => 'seans.php',     'label' => 'Seans',               'icon' => 'calendar'],
    ['id' => 'sss',       'href' => 'sss.php',       'label' => 'SSS',                 'icon' => 'message'],
    ['id' => 'blog',      'href' => 'blog.php',      'label' => 'Blog',              'icon' => 'pencil'],
    ['id' => 'iletisim',  'href' => 'iletisim.php',  'label' => 'İletişim',            'icon' => 'mail-open'],
];
?>
<header class="site-header" id="siteHeader">
  <div class="container">
    <div class="header-inner">
      <a href="index.php" class="logo">
        <span class="logo-name">Gonca LÖK</span>
        <span class="logo-sub">ICF Sertifikalı Koç</span>
      </a>
      <nav class="nav-links" id="navLinks" aria-label="Ana menü">
        <?php foreach ($navItems as $item): ?>
          <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"<?php echo ($item['id'] === $activePage) ? ' aria-current="page"' : ''; ?>><?php echo $item['label']; ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="nav-cta">
        <a href="iletisim.php" class="btn btn-primary" style="font-size:13px;padding:10px 20px;">Randevu Al</a>
      </div>
      <button class="hamburger" id="hamburger" aria-label="Menüyü aç/kapat" aria-expanded="false" aria-controls="mobileMenu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
<nav class="mobile-menu" id="mobileMenu" aria-label="Mobil menü">
  <?php foreach ($navItems as $item): ?>
    <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"<?php echo ($item['id'] === $activePage) ? ' aria-current="page"' : ''; ?>><?php echo icon($item['icon'], 17); ?> <?php echo $item['label']; ?></a>
  <?php endforeach; ?>
  <div class="mobile-cta btn-group">
    <a href="iletisim.php" class="btn btn-primary btn-full">Randevu Al</a>
  </div>
</nav>
