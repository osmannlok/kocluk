<?php
$headerUser = current_user();
$headerOrg = $headerUser['organization_name'] ?? '';
?>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-side-brand">
      <div class="logo-name">Gonca LÖK</div>
      <div class="logo-sub"><?php echo htmlspecialchars((string) ($headerOrg !== '' ? $headerOrg : 'Panel'), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <nav class="admin-nav" aria-label="Panel navigasyonu">
      <a href="admin.php?action=dashboard" class="admin-nav-link<?php echo (($action ?? '') === 'dashboard') ? ' active' : ''; ?>"><span class="admin-nav-icon"><?php echo icon("chart", 18); ?></span> Genel Bakış</a>
      <a href="admin.php?action=list" class="admin-nav-link<?php echo (($action ?? '') === 'list') ? ' active' : ''; ?>"><span class="admin-nav-icon"><?php echo icon("file-text", 18); ?></span> Yazılar</a>
      <a href="admin.php?action=students" class="admin-nav-link<?php echo in_array(($action ?? ''), ['students', 'student-edit', 'student-view', 'student-academics', 'student-career', 'student-risk'], true) ? ' active' : ''; ?>"><span class="admin-nav-icon"><?php echo icon("graduation", 18); ?></span> Öğrenciler</a>
      <a href="admin.php?action=notifications" class="admin-nav-link<?php echo (($action ?? '') === 'notifications') ? ' active' : ''; ?>"><span class="admin-nav-icon"><?php echo icon("mail", 18); ?></span> Bildirimler</a>
      <a href="admin.php?action=reports" class="admin-nav-link<?php echo in_array(($action ?? ''), ['reports', 'report-pdf', 'report-overview-pdf'], true) ? ' active' : ''; ?>"><span class="admin-nav-icon"><?php echo icon("file-text", 18); ?></span> Raporlar</a>
      <a href="admin.php?action=appointments" class="admin-nav-link<?php echo in_array(($action ?? ''), ['appointments', 'appointments-edit', 'appointments-schedule'], true) ? ' active' : ''; ?>"><span class="admin-nav-icon"><?php echo icon("calendar-month", 18); ?></span> Randevular</a>
      <a href="admin.php?action=appointments-schedule" class="admin-nav-link<?php echo (($action ?? '') === 'appointments-schedule') ? ' active' : ''; ?>"><span class="admin-nav-icon"><?php echo icon("clock", 18); ?></span> Dolu Saatler</a>
      <a href="admin.php?action=edit" class="admin-nav-link<?php echo (($action ?? '') === 'edit' && empty($_GET['id'])) ? ' active' : ''; ?>"><span class="admin-nav-icon"><?php echo icon("pencil", 18); ?></span> Yeni Yazı Ekle</a>
      <a href="admin.php?action=password" class="admin-nav-link<?php echo (($action ?? '') === 'password') ? ' active' : ''; ?>"><span class="admin-nav-icon"><?php echo icon("key", 18); ?></span> Şifre Değiştir</a>
      <a href="blog.php" target="_blank" class="admin-nav-link" rel="noopener"><span class="admin-nav-icon"><?php echo icon("globe", 18); ?></span> Site Önizle</a>
    </nav>
    <div class="admin-side-footer">
      <?php if ($headerUser): ?>
        <div class="admin-muted" style="font-size:12px;margin-bottom:8px;"><?php echo htmlspecialchars((string) $headerUser['name'], ENT_QUOTES, 'UTF-8'); ?><br><span style="opacity:.75;"><?php echo htmlspecialchars((string) $headerUser['email'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <?php endif; ?>
      <form method="post" action="admin.php?action=logout">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string) (function_exists('platform_csrf_token') ? platform_csrf_token() : ''), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="admin-logout-btn"><?php echo icon("logout", 16); ?> Çıkış</button>
      </form>
    </div>
  </aside>
  <?php if (!empty($adminFlash)): ?>
  <div class="admin-toast-container" role="status">
    <div class="admin-toast admin-toast-<?php echo htmlspecialchars((string)($adminFlash['type'] ?? 'success'), ENT_QUOTES, 'UTF-8'); ?> show">
      <div class="admin-toast-icon"><?php echo (($adminFlash['type'] ?? '') === 'error') ? icon('x', 18) : icon('check', 18); ?></div>
      <div class="admin-toast-text"><?php echo htmlspecialchars((string)($adminFlash['msg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>
  <?php endif; ?>
  <main id="icerik" class="admin-main">
