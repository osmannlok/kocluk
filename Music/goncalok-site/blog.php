<?php
// ============================================================
// GONCA LÖK — Blog (PHP)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sanitize.php';

$activePage = 'blog';
$posts = db_get_published_posts();

$activePost = null;
$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
if ($slug !== '') {
    $activePost = db_get_post($slug);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Gonca LÖK blogu: Öğrenci koçluğu, eğitim koçluğu ve kariyer koçluğu üzerine rehber yazılar, stratejiler ve deneyimler.">
  <title>Blog | Gonca LÖK</title>

  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
  <link rel="canonical" href="https://www.goncalokofficial.com/blog.php">
  <meta property="og:title" content="Blog | Gonca LÖK">
  <meta property="og:description" content="Gonca LÖK blogu: Öğrenci koçluğu, eğitim koçluğu ve kariyer koçluğu üzerine rehber yazılar, stratejiler ve deneyimler.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://www.goncalokofficial.com/blog.php">
  <meta property="og:site_name" content="Gonca LÖK">
  <meta property="og:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Blog | Gonca LÖK">
  <meta name="twitter:description" content="Gonca LÖK blogu: Öğrenci koçluğu, eğitim koçluğu ve kariyer koçluğu üzerine rehber yazılar, stratejiler ve deneyimler.">
  <meta name="twitter:image" content="https://www.goncalokofficial.com/assets/images/gonca-lok-tanitim.jpg">

</head>
<body data-page="blog">
  <a class="skip-link" href="#icerik">İçeriğe geç</a>
<?php require_once __DIR__ . '/partials/header.php'; ?>
<?php require_once __DIR__ . '/partials/wa-sticky.php'; ?>

  <main id="icerik">

    <?php if ($activePost): ?>
    <section class="hero hero-cream hero-sm">
      <div class="hero-pattern"></div>
      <div class="container">
        <div class="hero-content narrow" style="padding:100px 0 56px;">
          <span class="hero-badge"><?php echo icon("pencil", 15); ?> Blog</span>
          <h1><?php echo htmlspecialchars($activePost['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="hero-sub"><?php echo htmlspecialchars($activePost['category'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int) $activePost['reading_time']; ?> dk okuma · <?php echo date('d F Y', strtotime($activePost['created_at'])); ?></p>
        </div>
      </div>
    </section>
    <?php if ($activePost['image']): ?>
    <section class="blog-post-hero">
      <div class="container">
        <img class="blog-post-hero-img" src="assets/images/<?php echo htmlspecialchars($activePost['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($activePost['title'], ENT_QUOTES, 'UTF-8'); ?>" width="1600" height="500">
      </div>
    </section>
    <?php endif; ?>
    <section class="section bg-white">
      <div class="container" style="max-width:800px;">
        <div class="card card-pad-lg" style="padding:36px 32px;">
          <div class="prose" style="font-size:16px;line-height:1.9;color:var(--charcoal);">
            <?php echo blog_sanitize_html((string) $activePost['content']); ?>
          </div>
          <div class="mt-32"><a href="blog.php" class="btn btn-secondary">← Tüm Yazılara Dön</a></div>
        </div>
      </div>
    </section>
    <?php else: ?>
    <section class="hero hero-cream hero-md">
      <div class="hero-pattern"></div>
      <div class="container">
        <div class="hero-content narrow" style="padding:100px 0 64px;">
          <span class="hero-badge"><?php echo icon("pencil", 15); ?> Blog</span>
          <h1>Öğrenci Koçluğu &amp; Kariyer <span class="gold-word">Koçluğu</span> Üzerine Rehberler</h1>
          <p class="hero-sub">Haftalık yayınlanan rehber yazıları, stratejiler ve deneyimler — öğrenci ve profesyoneller için iç içe.</p>
        </div>
      </div>
    </section>
    <section class="section bg-white">
      <div class="container">
        <?php if (empty($posts)): ?>
          <div class="card text-center" style="padding:48px 24px;">
            <div class="tc-icon-lg" aria-hidden="true"><?php echo icon("file-text", 40); ?></div>
            <h3>Henüz yayınlanmış yazı yok.</h3>
            <p class="mt-8">İlk rehber yazımız hazır!</p>
          </div>
        <?php else: ?>
          <div class="grid-3">
            <?php foreach ($posts as $post): ?>
            <article class="blog-card">
              <a class="blog-card-media" href="blog.php?slug=<?php echo urlencode($post['slug']); ?>" tabindex="-1" aria-hidden="true">
                <?php $cardImage = trim((string) ($post['image'] ?? '')); ?>
                <img src="assets/images/<?php echo htmlspecialchars($cardImage !== '' ? $cardImage : 'gonca-lok-tanitim.jpg', ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" width="640" height="360">
              </a>
              <div class="blog-card-body">
                <div class="blog-card-meta">
                  <span><?php echo htmlspecialchars($post['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <span aria-hidden="true">·</span>
                  <span><?php echo (int) $post['reading_time']; ?> dk</span>
                </div>
                <h3><a href="blog.php?slug=<?php echo urlencode($post['slug']); ?>"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                <p><?php
                  $excerpt = strip_tags((string) $post['content']);
                  $excerpt = mb_substr($excerpt, 0, 140, 'UTF-8');
                  echo htmlspecialchars(trim($excerpt), ENT_QUOTES, 'UTF-8');
                ?></p>
                <a href="blog.php?slug=<?php echo urlencode($post['slug']); ?>" class="blog-card-link">Devamını Oku <span aria-hidden="true">→</span></a>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
  <script src="assets/js/site.js?v=18"></script>
</body>
</html>
