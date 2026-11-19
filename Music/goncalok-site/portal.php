<?php
// ============================================================
// GONCA LÖK — Öğrenci / Veli Portalı (Faz 4)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/platform-db.php';
require_once __DIR__ . '/platform-report.php';

app_session_start('portal');

if (session_idle_expired(SESSION_IDLE_SECONDS_PORTAL)) {
    header('Location: portal.php?reason=idle');
    exit;
}

define('PORTAL_SESSION_KEY', 'goncalok_portal_auth');

$portalError = '';
$portalFlash = $_SESSION['portal_flash'] ?? null;
if ($portalFlash) { unset($_SESSION['portal_flash']); }

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $params = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']); }
    session_destroy();
    header('Location: portal.php');
    exit;
}

if (isset($_GET['reason']) && $_GET['reason'] === 'idle') {
    $portalError = 'Oturumunuzun süresi doldu. Lütfen size gönderilen bağlantıyı tekrar açın.';
}

if (!empty($_GET['token'])) {
    $resolved = platform_portal_resolve_token((string) $_GET['token']);
    if ($resolved) {
        session_regenerate_id(true); // token çalınmışsa eski oturum sabitleme koruması
        $_SESSION[PORTAL_SESSION_KEY] = $resolved;
        $_SESSION['portal_organization_id'] = (int) ($resolved['organization_id'] ?? 0) ?: null;
        $_SESSION['_last_activity'] = time();
        // Token URL'de kalmasın: oturuma alıp temiz adrese yönlendir
        header('Location: portal.php');
        exit;
    }
    app_log('WARNING', 'portal token rejected', ['ip' => client_ip()]);
    $portalError = 'Bu bağlantı geçersiz, iptal edilmiş veya süresi dolmuş. Lütfen Gonca Hanım’dan yeni bir bağlantı isteyin.';
}

if (!isset($_SESSION[PORTAL_SESSION_KEY])) {
    platform_db_ensure_tables();
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Portal Girişi | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="portal">
      <main class="portal-login-wrap">
        <div class="card portal-login-card">
          <span class="eyebrow">Gonca LÖK Koçluk</span>
          <h2>Kişisel Portalınıza Hoş Geldiniz</h2>
          <p class="admin-muted">Giriş yapmak için Gonca Hanım’ın size gönderdiği özel bağlantıyı kullanın. Bağlantıyı kimseyle paylaşmayın.</p>
          <?php if ($portalError): ?><div class="portal-alert"><?php echo platform_e($portalError); ?></div><?php endif; ?>
          <p class="admin-muted" style="margin-top:20px;font-size:12px;">
            Erişim bağlantısı 30 gün geçerlidir. Sorun yaşarsanız <a href="mailto:goncalokofficial@gmail.com" class="admin-link">goncalokofficial@gmail.com</a> adresine yazabilirsiniz.
          </p>
          <a href="iletisim.php" class="btn btn-secondary" style="margin-top:18px;">← Ana Sayfaya Dön</a>
        </div>
      </main>
    </body>
    </html>
    <?php
    exit;
}

$auth = $_SESSION[PORTAL_SESSION_KEY];
$studentId = (int) ($auth['student_id'] ?? 0);
$role = (string) ($auth['role'] ?? 'student');
if (!in_array($role, ['student', 'guardian'], true)) { $role = 'student'; }

$student = $studentId > 0 ? platform_get_student($studentId) : null;
if (!$student) {
    unset($_SESSION[PORTAL_SESSION_KEY]);
    header('Location: portal.php');
    exit;
}

platform_db_ensure_tables();
platform_csrf_token();

if (($_GET['download'] ?? '') === 'report') {
    if (!platform_csrf_valid((string) ($_GET['csrf'] ?? ''))) {
        http_response_code(403);
        echo 'Güvenlik tokenı geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
        exit;
    }
    [$from, $to] = platform_report_period();
    $pdf = new PlatformPdf();
    $filename = platform_write_student_report($pdf, $studentId, 'shared', $from, $to);
    platform_pdf_download($filename, $pdf->output());
}

// ---- Portal işlemleri ----

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string) ($_POST['portal_action'] ?? '');
    $token = (string) ($_POST['csrf'] ?? '');
    if (!platform_csrf_valid($token)) {
        http_response_code(403);
        echo 'Güvenlik tokenı geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
        exit;
    }
    $view = (string) ($_GET['view'] ?? 'ozet');

    if ($postAction === 'action-toggle' && $role === 'student') {
        $actionId = (int) ($_POST['id'] ?? 0);
        if ($actionId > 0 && platform_portal_can_open_action($studentId, $actionId)) {
            platform_toggle_action($actionId);
            $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Ödev durumu güncellendi.'];
        }
    }

    if ($postAction === 'consent-grant' && $role === 'student') {
        platform_grant_portal_consent($studentId);
        $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'KVKK onayınız kaydedildi.'];
    }

    header('Location: portal.php?view=' . rawurlencode($view));
    exit;
}

$views = $role === 'guardian'
    ? ['ozet' => 'Genel Bakış', 'seanslar' => 'Seans Özetleri', 'hedefler' => 'Hedefler', 'akademik' => 'Akademik']
    : ['ozet' => 'Genel Bakış', 'seanslar' => 'Seans Özetleri', 'hedefler' => 'Hedefler ve Ödevler', 'akademik' => 'Akademik', 'kariyer' => 'Kariyer', 'onay' => 'KVKK Onayı'];
$view = (string) ($_GET['view'] ?? 'ozet');
if (!array_key_exists($view, $views)) { $view = 'ozet'; }

$sessions = platform_portal_sessions($studentId);
$goals = platform_get_goals($studentId);
$tasks = platform_get_actions($studentId);
$subjects = platform_get_subjects($studentId);
$lessons = platform_get_lessons($studentId);
$exams = platform_get_exams($studentId);
$career = $role === 'student' ? platform_get_career_profile($studentId) : [];
$applications = $role === 'student' ? platform_get_applications($studentId) : [];

$openTaskCount = count(array_filter($tasks, function ($t) { return $t['status'] !== 'done'; }));
$activeGoalCount = count(array_filter($goals, function ($g) { return $g['status'] === 'active'; }));

function portal_e($value): string
{
    return platform_e((string) $value);
}

function portal_nav(array $views, string $active): void
{
    echo '<nav class="portal-nav">';
    foreach ($views as $key => $label) {
        echo '<a href="portal.php?view=' . portal_e($key) . '" class="' . ($active === $key ? 'active' : '') . '">' . portal_e($label) . '</a>';
    }
    echo '</nav>';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?php echo portal_e($role === 'guardian' ? 'Veli Portalı' : 'Öğrenci Portalı'); ?> | Gonca LÖK</title>

  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
  <link rel="stylesheet" href="assets/css/admin.css?v=18">

</head>
<body class="admin-body" data-page="portal">
  <header class="portal-topbar">
    <div class="portal-topbar-inner">
      <div>
        <div class="logo-name">Gonca LÖK</div>
        <div class="logo-sub"><?php echo portal_e($role === 'guardian' ? 'Veli Portalı' : 'Öğrenci Portalı'); ?></div>
      </div>
      <div class="portal-user">
        <span>Merhaba, <?php echo portal_e($role === 'guardian' ? ($student['guardian_name'] ?: 'Veli') : $student['first_name']); ?></span>
        <a href="portal.php?download=report&csrf=<?php echo rawurlencode(platform_csrf_token()); ?>" class="btn btn-secondary btn-mini"><?php echo icon("file-text", 15); ?> PDF Rapor</a>
        <a href="portal.php?logout=1" class="btn btn-secondary btn-mini">Çıkış</a>
      </div>
    </div>
  </header>

  <main class="portal-main">
    <div class="portal-container">
      <?php if ($portalFlash): ?>
        <div class="portal-alert portal-alert-<?php echo portal_e((string) ($portalFlash['type'] ?? 'success')); ?>"><?php echo portal_e((string) ($portalFlash['msg'] ?? '')); ?></div>
      <?php endif; ?>

      <?php portal_nav($views, $view); ?>

      <?php if ($view === 'ozet'): ?>
        <section class="portal-card mt-24">
          <span class="eyebrow"><?php echo portal_e($student['student_code']); ?></span>
          <h1><?php echo portal_e($student['first_name'] . ' ' . $student['last_name']); ?></h1>
          <p class="admin-muted">
            <?php echo portal_e($student['coaching_type']); ?>
            <?php if ($student['school']): ?> · <?php echo portal_e($student['school']); ?><?php endif; ?>
            <?php if ($student['grade_level']): ?> · <?php echo portal_e($student['grade_level']); ?><?php endif; ?>
          </p>
          <div class="portal-stats mt-24">
            <div><strong><?php echo count($sessions); ?></strong><span>Seans Özeti</span></div>
            <div><strong><?php echo $activeGoalCount; ?></strong><span>Aktif Hedef</span></div>
            <div><strong><?php echo $openTaskCount; ?></strong><span>Açık Ödev</span></div>
            <div><strong><?php echo count($exams); ?></strong><span>Sınav Kaydı</span></div>
          </div>
        </section>

        <?php if ($sessions): ?>
          <?php $last = $sessions[0]; ?>
          <section class="portal-card mt-24">
            <div class="admin-section-head"><h3>Son Seans Özeti</h3><span class="admin-muted"><?php echo portal_e($last['session_date']); ?></span></div>
            <?php if ($last['shared_note']): ?>
              <p class="portal-note"><?php echo nl2br(portal_e($last['shared_note'])); ?></p>
            <?php else: ?>
              <p class="admin-muted">Bu seans için henüz paylaşılan bir özet yok.</p>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($openTaskCount > 0): ?>
          <section class="portal-card mt-24">
            <div class="admin-section-head"><h3>Yaklaşan Ödevler</h3></div>
            <?php foreach (array_slice($tasks, 0, 4) as $t): ?>
              <?php if ($t['status'] === 'done') { continue; } ?>
              <div class="portal-row"><span><?php echo portal_e($t['title']); ?></span><span class="admin-muted"><?php echo portal_e($t['due_date'] ?: 'Termin yok'); ?></span></div>
            <?php endforeach; ?>
            <a href="portal.php?view=hedefler" class="admin-link" style="display:inline-block;margin-top:12px;">Tümünü gör →</a>
          </section>
        <?php endif; ?>

      <?php elseif ($view === 'seanslar'): ?>
        <section class="portal-card mt-24">
          <div class="admin-section-head"><h3>Seans Özetleri</h3><span class="admin-muted">Yalnızca sizinle paylaşılan notlar gösterilir</span></div>
          <?php if (!$sessions): ?>
            <p class="admin-muted">Henüz seans özeti bulunmuyor.</p>
          <?php endif; ?>
          <?php foreach ($sessions as $s): ?>
            <article class="portal-session">
              <div class="admin-section-head">
                <strong><?php echo portal_e($s['session_date'] . ' ' . substr((string) $s['session_time'], 0, 5)); ?></strong>
                <?php if ($s['template_name']): ?><span class="admin-pill admin-pill-pending"><?php echo portal_e($s['template_name']); ?></span><?php endif; ?>
              </div>
              <?php if ($s['shared_note']): ?>
                <p class="portal-note"><?php echo nl2br(portal_e($s['shared_note'])); ?></p>
              <?php else: ?>
                <p class="admin-muted">Paylaşılan özet bulunmuyor.</p>
              <?php endif; ?>
              <?php if ($role === 'student' && ($s['mood_before'] || $s['mood_after'])): ?>
                <p class="admin-muted" style="font-size:12px;">Ruh hali: <?php echo portal_e($s['mood_before'] ?: '—'); ?> → <?php echo portal_e($s['mood_after'] ?: '—'); ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </section>

      <?php elseif ($view === 'hedefler'): ?>
        <section class="portal-card mt-24">
          <div class="admin-section-head"><h3>Hedefler</h3></div>
          <?php if (!$goals): ?><p class="admin-muted">Henüz hedef eklenmedi.</p><?php endif; ?>
          <?php foreach ($goals as $g): ?>
            <article class="portal-session">
              <div class="admin-section-head">
                <strong><?php echo portal_e($g['title']); ?></strong>
                <span class="admin-pill admin-pill-<?php echo $g['status'] === 'active' ? 'active' : 'pending'; ?>"><?php echo portal_e(['active' => 'Aktif', 'completed' => 'Tamamlandı', 'paused' => 'Beklemede'][$g['status']] ?? $g['status']); ?></span>
              </div>
              <?php if ($g['description']): ?><p class="portal-note"><?php echo nl2br(portal_e($g['description'])); ?></p><?php endif; ?>
              <div class="portal-progress"><span style="width:<?php echo max(0, min(100, (int) $g['progress'])); ?>%;"></span></div>
              <p class="admin-muted" style="font-size:12px;">İlerleme: %<?php echo (int) $g['progress']; ?> · Hedef tarih: <?php echo portal_e($g['target_date'] ?: 'Yok'); ?></p>
            </article>
          <?php endforeach; ?>
        </section>

        <section class="portal-card mt-24">
          <div class="admin-section-head"><h3>Ödevler</h3><?php if ($role === 'student'): ?><span class="admin-muted">Tamamladıkça işaretleyin</span><?php endif; ?></div>
          <?php if (!$tasks): ?><p class="admin-muted">Henüz ödev eklenmedi.</p><?php endif; ?>
          <?php foreach ($tasks as $t): ?>
            <div class="portal-task">
              <div>
                <strong class="<?php echo $t['status'] === 'done' ? 'portal-done' : ''; ?>"><?php echo portal_e($t['title']); ?></strong>
                <div class="admin-muted" style="font-size:12px;"><?php echo portal_e($t['due_date'] ? 'Termin: ' . $t['due_date'] : 'Termin yok'); ?><?php if ($t['goal_title']): ?> · <?php echo portal_e($t['goal_title']); ?><?php endif; ?></div>
              </div>
              <?php if ($role === 'student'): ?>
                <form method="post" action="portal.php?view=hedefler">
                  <input type="hidden" name="csrf" value="<?php echo portal_e(platform_csrf_token()); ?>">
                  <input type="hidden" name="portal_action" value="action-toggle">
                  <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                  <button type="submit" class="btn btn-mini <?php echo $t['status'] === 'done' ? 'btn-secondary' : 'btn-primary'; ?>"><?php echo $t['status'] === 'done' ? 'Geri Aç' : 'Tamamla'; ?></button>
                </form>
              <?php else: ?>
                <span class="admin-pill admin-pill-<?php echo $t['status'] === 'done' ? 'active' : 'pending'; ?>"><?php echo portal_e($t['status'] === 'done' ? 'Tamamlandı' : 'Açık'); ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </section>

      <?php elseif ($view === 'akademik'): ?>
        <section class="portal-card mt-24">
          <div class="admin-section-head"><h3>Dersler</h3></div>
          <?php if (!$subjects): ?><p class="admin-muted">Henüz ders kaydı yok.</p><?php endif; ?>
          <?php foreach ($subjects as $s): ?>
            <div class="portal-row">
              <span><strong><?php echo portal_e($s['name']); ?></strong><?php if ($s['teacher']): ?> <span class="admin-muted">· <?php echo portal_e($s['teacher']); ?></span><?php endif; ?></span>
              <span class="admin-muted"><?php echo portal_e($s['current_grade'] ?: '—'); ?> / <?php echo portal_e($s['target_grade'] ?: '—'); ?></span>
            </div>
          <?php endforeach; ?>
        </section>

        <section class="portal-card mt-24">
          <div class="admin-section-head"><h3>Çalışma Kayıtları</h3></div>
          <?php if (!$lessons): ?><p class="admin-muted">Henüz çalışma kaydı yok.</p><?php endif; ?>
          <?php foreach (array_slice($lessons, 0, 10) as $l): ?>
            <div class="portal-row">
              <span><strong><?php echo portal_e($l['topic']); ?></strong><br><span class="admin-muted" style="font-size:12px;"><?php echo portal_e($l['lesson_date']); ?> · <?php echo (int) $l['duration_min']; ?> dk</span></span>
              <?php if ($l['homework']): ?><span class="admin-muted" style="font-size:12px;max-width:45%;"><?php echo nl2br(portal_e($l['homework'])); ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </section>

        <section class="portal-card mt-24">
          <div class="admin-section-head"><h3>Sınavlar</h3></div>
          <?php if (!$exams): ?><p class="admin-muted">Henüz sınav kaydı yok.</p><?php endif; ?>
          <?php foreach ($exams as $e): ?>
            <div class="portal-row">
              <span><strong><?php echo portal_e($e['exam_name']); ?></strong><br><span class="admin-muted" style="font-size:12px;"><?php echo portal_e($e['exam_date']); ?></span></span>
              <span><?php echo portal_e($e['score'] ?? '—'); ?> / <?php echo portal_e($e['max_score']); ?> <span class="admin-muted">(hedef: <?php echo portal_e($e['target_score'] ?? '—'); ?>)</span></span>
            </div>
          <?php endforeach; ?>
        </section>

      <?php elseif ($view === 'kariyer' && $role === 'student'): ?>
        <section class="portal-card mt-24">
          <div class="admin-section-head"><h3>Kariyer Hedeflerim</h3></div>
          <div class="portal-row"><span>Hedef Üniversite</span><strong><?php echo portal_e($career['target_university'] ?: '—'); ?></strong></div>
          <div class="portal-row"><span>Hedef Bölüm</span><strong><?php echo portal_e($career['target_department'] ?: '—'); ?></strong></div>
          <div class="portal-row"><span>Hedef Rol</span><strong><?php echo portal_e($career['target_role'] ?: '—'); ?></strong></div>
          <?php if ($career['skills']): ?><div class="portal-note mt-16"><strong>Yetkinlikler</strong><br><?php echo nl2br(portal_e($career['skills'])); ?></div><?php endif; ?>
          <?php if ($career['interests']): ?><div class="portal-note mt-16"><strong>İlgi Alanları</strong><br><?php echo nl2br(portal_e($career['interests'])); ?></div><?php endif; ?>
        </section>

        <section class="portal-card mt-24">
          <div class="admin-section-head"><h3>Başvurularım</h3></div>
          <?php if (!$applications): ?><p class="admin-muted">Henüz başvuru kaydı yok.</p><?php endif; ?>
          <?php foreach ($applications as $a): ?>
            <div class="portal-row">
              <span><strong><?php echo portal_e($a['organization']); ?></strong><br><span class="admin-muted" style="font-size:12px;"><?php echo portal_e($a['position'] ?: '—'); ?> · Son tarih: <?php echo portal_e($a['deadline'] ?: '—'); ?></span></span>
              <span class="admin-pill admin-pill-<?php echo in_array($a['status'], ['accepted', 'submitted'], true) ? 'active' : 'pending'; ?>"><?php echo portal_e(['preparing' => 'Hazırlanıyor', 'submitted' => 'Gönderildi', 'interview' => 'Görüşme', 'accepted' => 'Kabul', 'rejected' => 'Reddedildi'][$a['status']] ?? $a['status']); ?></span>
            </div>
          <?php endforeach; ?>
        </section>

      <?php elseif ($view === 'onay' && $role === 'student'): ?>
        <section class="portal-card mt-24">
          <div class="admin-section-head"><h3>KVKK / Aydınlatma Onayı</h3></div>
          <?php if ($student['consent_at']): ?>
            <p class="portal-alert portal-alert-success">Onayınız kayıtlı: <?php echo portal_e(substr((string) $student['consent_at'], 0, 16)); ?> (v<?php echo portal_e($student['consent_version'] ?: '1.1'); ?>)</p>
          <?php else: ?>
            <p class="admin-muted">Kişisel verilerinizin koçluk süreci kapsamında işlenmesine ilişkin aydınlatma metnini okudunuz mu? Onay vermek için aşağıdaki butonu kullanın.</p>
            <div class="admin-risk-note mt-16">
              <strong>Aydınlatma özeti:</strong> Kişisel verileriniz, koçluk hizmetinin sunulması, seans kayıtlarının tutulması ve gelişiminizin takibi amacıyla işlenir. Verileriniz satılmaz, pazarlama amacıyla paylaşılmaz; yalnızca kanunen zorunlu olduğu ve sizin verdiğiniz yetkiler kapsamında aktarılır. Dilediğiniz zaman erişim, düzeltme ve silinme talebinde bulunabilirsiniz (KVKK m.11). Ayrıntılar: <a href="kvkk.php" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a> ve <a href="gizlilik.php" target="_blank" rel="noopener">Gizlilik Politikası</a>.
            </div>
            <form method="post" action="portal.php?view=onay" class="mt-16">
              <input type="hidden" name="csrf" value="<?php echo portal_e(platform_csrf_token()); ?>">
              <input type="hidden" name="portal_action" value="consent-grant">
              <label class="admin-check-row"><input type="checkbox" required> Aydınlatma metnini okudum, onaylıyorum.</label>
              <button type="submit" class="btn btn-primary mt-16">Onayı Kaydet</button>
            </form>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
