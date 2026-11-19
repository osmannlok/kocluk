<?php
// ============================================================
// GONCA LÖK — Admin Paneli (org-scoped, çok kullanıcılı)
// Giriş: users tablosu (owner/coach/admin). Çok org'lu çalışır.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/platform-db.php';
require_once __DIR__ . '/platform-report.php';
require_once __DIR__ . '/sanitize.php';

app_session_start('admin');

$action = $_GET['action'] ?? 'dashboard';
$adminAuthed = current_user() !== null;

if ($adminAuthed && session_idle_expired(SESSION_IDLE_SECONDS_ADMIN)) {
    $adminAuthed = false;
    header('Location: admin.php?action=login&reason=idle');
    exit;
}

$adminFlash = $_SESSION['admin_flash'] ?? null;
if ($adminFlash) { unset($_SESSION['admin_flash']); }

// ---- Giriş işlemi ----
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginToken = $_POST['csrf'] ?? '';
    if (!platform_csrf_valid($loginToken)) {
        http_response_code(403);
        echo 'Güvenlik tokenı geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
        exit;
    }
    $result = auth_attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($result['ok']) {
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Giriş başarılı. Hoş geldiniz!'];
        header('Location: admin.php?action=dashboard');
        exit;
    }
    $loginError = $result['error'];
}

// ---- Admin koruması ----
// Public (oturum gerektirmeyen) eylemler:
$publicActions = ['login', 'appointments-public-save', 'appointments-check-slot', 'appointments-public-list', 'appointments-slots', 'appointments-week'];
if (!$adminAuthed && !in_array($action, $publicActions, true)) {
    header('Location: admin.php?action=login');
    exit;
}

if ($adminAuthed) {
    platform_db_ensure_tables();
    platform_csrf_token();
}

// ---- Platform yardımcıları ----
function platform_url(string $action, array $params = []): string
{
    $params = array_merge(['action' => $action, 'csrf' => platform_csrf_token()], $params);
    return 'admin.php?' . http_build_query($params);
}

function platform_check_csrf(): void
{
    $token = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
    if (!platform_csrf_valid($token)) {
        http_response_code(403);
        echo 'Güvenlik tokenı geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
        exit;
    }
}

function platform_student_tabs(int $studentId, string $active): void
{
    $tabs = [
        'student-view' => '<?php echo icon("folder", 18); ?> Dosya',
        'student-academics' => '<?php echo icon("book", 18); ?> Akademik',
        'student-career' => '<?php echo icon("briefcase", 18); ?> Kariyer',
        'student-risk' => '<?php echo icon("alert", 16); ?>️ Risk Takibi',
    ];
    echo '<nav class="admin-student-tabs">';
    foreach ($tabs as $action => $label) {
        echo '<a href="admin.php?action=' . platform_e($action) . '&id=' . $studentId . '" class="' . ($active === $action ? 'active' : '') . '">' . platform_e($label) . '</a>';
    }
    echo '</nav>';
}

function platform_admin_notify(int $studentId, string $kind, string $subject, string $body, string $role = 'student'): void
{
    $student = platform_get_student($studentId);
    if (!$student) { return; }
    $email = $role === 'guardian' ? (string) ($student['guardian_email'] ?? '') : (string) ($student['email'] ?? '');
    if ($email === '') { return; }
    platform_notify_student($studentId, $kind, $email, $subject, $body);
}

// ---- Çıkış ----
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $params = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']); }
    session_destroy();
    header('Location: admin.php?action=login');
    exit;
}

// ============================================================
// KOÇLUK PLATFORMU — işlem akışları
// ============================================================

// ---- Şifre değiştir (self-service) ----
if ($action === 'password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $new2 = (string) ($_POST['new_password_confirm'] ?? '');
    $user = current_user();
    $dbUser = $user ? auth_find_user_by_email((string) $user['email']) : null;
    if (!$dbUser || !password_verify($current, (string) $dbUser['password_hash'])) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Mevcut şifreniz hatalı.'];
    } elseif (strlen($new) < 12) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Yeni şifreniz en az 12 karakter olmalı.'];
    } elseif ($new !== $new2) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Yeni şifreler eşleşmiyor.'];
    } else {
        db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute([':hash' => password_hash($new, PASSWORD_DEFAULT), ':id' => (int) $dbUser['id']]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Şifreniz güncellendi.'];
        app_log('INFO', 'password changed', ['user_id' => (int) $dbUser['id']]);
    }
    header('Location: admin.php?action=password');
    exit;
}

if ($action === 'student-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['id'] ?? 0);
    $data = [
        'first_name' => trim((string) ($_POST['first_name'] ?? '')),
        'last_name' => trim((string) ($_POST['last_name'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
        'school' => trim((string) ($_POST['school'] ?? '')),
        'grade_level' => trim((string) ($_POST['grade_level'] ?? '')),
        'coaching_type' => trim((string) ($_POST['coaching_type'] ?? 'Öğrenci Koçluğu')),
        'status' => trim((string) ($_POST['status'] ?? 'active')),
        'risk_level' => trim((string) ($_POST['risk_level'] ?? 'low')),
        'guardian_name' => trim((string) ($_POST['guardian_name'] ?? '')),
        'guardian_phone' => trim((string) ($_POST['guardian_phone'] ?? '')),
        'guardian_email' => trim((string) ($_POST['guardian_email'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'consent' => isset($_POST['consent']),
    ];
    if (!array_key_exists($data['status'], platform_student_statuses())) { $data['status'] = 'active'; }
    if (!array_key_exists($data['risk_level'], platform_risk_levels())) { $data['risk_level'] = 'low'; }

    if ($data['first_name'] === '' || $data['last_name'] === '' || $data['phone'] === '') {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Ad, soyad ve telefon zorunludur.'];
        header('Location: admin.php?action=student-edit' . ($sid > 0 ? '&id=' . $sid : ''));
        exit;
    }

    try {
        if ($sid > 0) {
            platform_update_student($sid, $data);
            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Öğrenci güncellendi.'];
        } else {
            $sid = platform_create_student($data);
            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Öğrenci kaydı oluşturuldu.'];
        }
    } catch (Throwable $e) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Öğrenci kaydedilemedi. Lütfen tekrar deneyin.'];
        header('Location: admin.php?action=student-edit' . ($sid > 0 ? '&id=' . $sid : ''));
        exit;
    }
    header('Location: admin.php?action=student-view&id=' . $sid);
    exit;
}

if ($action === 'student-status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($sid > 0 && platform_set_student_status($sid, $status)) {
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Öğrenci durumu güncellendi.'];
    } else {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Geçersiz durum.'];
    }
    header('Location: admin.php?action=student-view&id=' . $sid);
    exit;
}

if ($action === 'student-link-appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    $aid = (int) ($_POST['appointment_id'] ?? 0);
    if ($sid > 0 && $aid > 0) {
        platform_link_appointment_student($aid, $sid);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Randevu öğrenciye bağlandı.'];
    }
    header('Location: admin.php?action=student-view&id=' . $sid);
    exit;
}

if ($action === 'session-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $date = trim((string) ($_POST['session_date'] ?? ''));
    $time = trim((string) ($_POST['session_time'] ?? '09:00'));
    try {
        [$date, $time] = db_normalize_slot($date, $time);
        $sharedNote = trim((string) ($_POST['shared_note'] ?? ''));
        platform_save_session($id, [
            'student_id' => $studentId,
            'appointment_id' => (int) ($_POST['appointment_id'] ?? 0),
            'template_id' => (int) ($_POST['template_id'] ?? 0),
            'session_date' => $date,
            'session_time' => $time,
            'session_type' => trim((string) ($_POST['session_type'] ?? 'Düzenli Seans')),
            'status' => in_array($_POST['status'] ?? '', ['planned', 'completed', 'cancelled', 'no_show'], true) ? $_POST['status'] : 'planned',
            'duration_min' => 60,
            'mood_before' => trim((string) ($_POST['mood_before'] ?? '')),
            'mood_after' => trim((string) ($_POST['mood_after'] ?? '')),
            'shared_note' => $sharedNote,
            'private_note' => trim((string) ($_POST['private_note'] ?? '')),
        ]);
        if ($sharedNote !== '') {
            platform_admin_notify(
                $studentId,
                'session_shared_note',
                'Gonca LÖK — Yeni Seans Özetiniz',
                "Merhaba,\n\n" . $date . " tarihli seansınıza ait özet portalınıza eklendi.\n\nGonca LÖK"
            );
        }
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Seans kaydedildi.'];
    } catch (Throwable $e) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Seans kaydedilemedi: ' . $e->getMessage()];
    }
    header('Location: admin.php?action=student-view&id=' . $studentId);
    exit;
}

if ($action === 'session-delete') {
    platform_check_csrf();
    $studentId = (int) ($_GET['student_id'] ?? 0);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) { platform_delete_session($id); }
    $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Seans silindi.'];
    header('Location: admin.php?action=student-view&id=' . $studentId);
    exit;
}

if ($action === 'goal-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '' || $studentId <= 0) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Hedef başlığı zorunludur.'];
    } else {
        platform_save_goal($id, [
            'student_id' => $studentId,
            'session_id' => (int) ($_POST['session_id'] ?? 0),
            'title' => $title,
            'category' => trim((string) ($_POST['category'] ?? 'Akademik')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'target_date' => trim((string) ($_POST['target_date'] ?? '')),
            'status' => in_array($_POST['status'] ?? '', ['active', 'completed', 'paused'], true) ? $_POST['status'] : 'active',
            'progress' => (int) ($_POST['progress'] ?? 0),
        ]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Hedef kaydedildi.'];
    }
    header('Location: admin.php?action=student-view&id=' . $studentId);
    exit;
}

if ($action === 'goal-delete') {
    platform_check_csrf();
    $studentId = (int) ($_GET['student_id'] ?? 0);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) { platform_delete_goal($id); }
    $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Hedef silindi.'];
    header('Location: admin.php?action=student-view&id=' . $studentId);
    exit;
}

if ($action === 'action-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '' || $studentId <= 0) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Ödev başlığı zorunludur.'];
    } else {
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        $status = in_array($_POST['status'] ?? '', ['open', 'in_progress', 'done', 'overdue'], true) ? $_POST['status'] : 'open';
        platform_save_action($id, [
            'student_id' => $studentId,
            'session_id' => (int) ($_POST['session_id'] ?? 0),
            'goal_id' => (int) ($_POST['goal_id'] ?? 0),
            'title' => $title,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'due_date' => $dueDate,
            'status' => $status,
        ]);
        if ($id === 0 && $status !== 'done') {
            platform_admin_notify(
                $studentId,
                'action_created',
                'Gonca LÖK — Yeni Ödev',
                "Merhaba,\n\nYeni ödev: " . $title . ($dueDate !== '' ? "\nTermin: " . $dueDate : '') . "\n\nGonca LÖK"
            );
        }
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Ödev kaydedildi.'];
    }
    header('Location: admin.php?action=student-view&id=' . $studentId);
    exit;
}

if ($action === 'action-toggle') {
    platform_check_csrf();
    $studentId = (int) ($_GET['student_id'] ?? 0);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) { platform_toggle_action($id); }
    header('Location: admin.php?action=student-view&id=' . $studentId);
    exit;
}

if ($action === 'action-delete') {
    platform_check_csrf();
    $studentId = (int) ($_GET['student_id'] ?? 0);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) { platform_delete_action($id); }
    $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Ödev silindi.'];
    header('Location: admin.php?action=student-view&id=' . $studentId);
    exit;
}

// ---- Faz 3: akademik / kariyer / risk işlemleri ----

if ($action === 'subject-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($sid > 0 && $name !== '') {
        platform_save_subject((int) ($_POST['id'] ?? 0), [
            'student_id' => $sid,
            'name' => $name,
            'teacher' => trim((string) ($_POST['teacher'] ?? '')),
            'term' => trim((string) ($_POST['term'] ?? '')),
            'target_grade' => trim((string) ($_POST['target_grade'] ?? '')),
            'current_grade' => trim((string) ($_POST['current_grade'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Ders kaydedildi.'];
    } else {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Ders adı zorunludur.'];
    }
    header('Location: admin.php?action=student-academics&id=' . $sid);
    exit;
}

if ($action === 'subject-delete') {
    platform_check_csrf();
    $sid = (int) ($_GET['student_id'] ?? 0);
    if ((int) ($_GET['id'] ?? 0) > 0) { platform_delete_subject((int) $_GET['id']); }
    header('Location: admin.php?action=student-academics&id=' . $sid);
    exit;
}

if ($action === 'lesson-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    $topic = trim((string) ($_POST['topic'] ?? ''));
    $date = trim((string) ($_POST['lesson_date'] ?? ''));
    if ($sid > 0 && $topic !== '' && $date !== '') {
        platform_save_lesson((int) ($_POST['id'] ?? 0), [
            'student_id' => $sid,
            'subject_id' => (int) ($_POST['subject_id'] ?? 0),
            'lesson_date' => $date,
            'topic' => $topic,
            'duration_min' => (int) ($_POST['duration_min'] ?? 60),
            'status' => trim((string) ($_POST['status'] ?? 'planned')),
            'coach_note' => trim((string) ($_POST['coach_note'] ?? '')),
            'student_feedback' => trim((string) ($_POST['student_feedback'] ?? '')),
            'homework' => trim((string) ($_POST['homework'] ?? '')),
        ]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Çalışma kaydı kaydedildi.'];
    } else {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Konu ve tarih zorunludur.'];
    }
    header('Location: admin.php?action=student-academics&id=' . $sid);
    exit;
}

if ($action === 'lesson-delete') {
    platform_check_csrf();
    $sid = (int) ($_GET['student_id'] ?? 0);
    if ((int) ($_GET['id'] ?? 0) > 0) { platform_delete_lesson((int) $_GET['id']); }
    header('Location: admin.php?action=student-academics&id=' . $sid);
    exit;
}

if ($action === 'exam-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    $name = trim((string) ($_POST['exam_name'] ?? ''));
    $date = trim((string) ($_POST['exam_date'] ?? ''));
    if ($sid > 0 && $name !== '' && $date !== '') {
        platform_save_exam((int) ($_POST['id'] ?? 0), [
            'student_id' => $sid,
            'subject_id' => (int) ($_POST['subject_id'] ?? 0),
            'exam_name' => $name,
            'exam_date' => $date,
            'score' => trim((string) ($_POST['score'] ?? '')),
            'target_score' => trim((string) ($_POST['target_score'] ?? '')),
            'max_score' => trim((string) ($_POST['max_score'] ?? '100')),
            'note' => trim((string) ($_POST['note'] ?? '')),
        ]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Sınav kaydedildi.'];
    } else {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Sınav adı ve tarihi zorunludur.'];
    }
    header('Location: admin.php?action=student-academics&id=' . $sid);
    exit;
}

if ($action === 'exam-delete') {
    platform_check_csrf();
    $sid = (int) ($_GET['student_id'] ?? 0);
    if ((int) ($_GET['id'] ?? 0) > 0) { platform_delete_exam((int) $_GET['id']); }
    header('Location: admin.php?action=student-academics&id=' . $sid);
    exit;
}

if ($action === 'career-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    if ($sid > 0) {
        platform_save_career_profile($sid, [
            'target_university' => trim((string) ($_POST['target_university'] ?? '')),
            'target_department' => trim((string) ($_POST['target_department'] ?? '')),
            'target_role' => trim((string) ($_POST['target_role'] ?? '')),
            'skills' => trim((string) ($_POST['skills'] ?? '')),
            'interests' => trim((string) ($_POST['interests'] ?? '')),
            'cv_path' => trim((string) ($_POST['cv_path'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Kariyer profili kaydedildi.'];
    }
    header('Location: admin.php?action=student-career&id=' . $sid);
    exit;
}

if ($action === 'application-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    $org = trim((string) ($_POST['organization'] ?? ''));
    if ($sid > 0 && $org !== '') {
        platform_save_application((int) ($_POST['id'] ?? 0), [
            'student_id' => $sid,
            'kind' => trim((string) ($_POST['kind'] ?? 'university')),
            'organization' => $org,
            'position' => trim((string) ($_POST['position'] ?? '')),
            'deadline' => trim((string) ($_POST['deadline'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'preparing')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Başvuru kaydedildi.'];
    } else {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Kurum adı zorunludur.'];
    }
    header('Location: admin.php?action=student-career&id=' . $sid);
    exit;
}

if ($action === 'application-delete') {
    platform_check_csrf();
    $sid = (int) ($_GET['student_id'] ?? 0);
    if ((int) ($_GET['id'] ?? 0) > 0) { platform_delete_application((int) $_GET['id']); }
    header('Location: admin.php?action=student-career&id=' . $sid);
    exit;
}

if ($action === 'risk-evaluate') {
    platform_check_csrf();
    $sid = (int) ($_GET['id'] ?? 0);
    $found = $sid > 0 ? platform_evaluate_student_risk($sid) : [];
    $_SESSION['admin_flash'] = $found
        ? ['type' => 'error', 'msg' => 'Yeni uyarılar: ' . implode(', ', $found) . '.']
        : ['type' => 'success', 'msg' => 'Yeni risk uyarısı oluşmadı.'];
    header('Location: admin.php?action=student-risk&id=' . $sid);
    exit;
}

if ($action === 'risk-status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    platform_set_risk_flag_status((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? 'open'), trim((string) ($_POST['resolution_note'] ?? '')));
    header('Location: admin.php?action=student-risk&id=' . $sid);
    exit;
}

if ($action === 'intervention-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    $text = trim((string) ($_POST['action'] ?? ''));
    if ($sid > 0 && $text !== '') {
        platform_save_intervention((int) ($_POST['id'] ?? 0), [
            'student_id' => $sid,
            'flag_id' => (int) ($_POST['flag_id'] ?? 0),
            'action' => $text,
            'owner' => trim((string) ($_POST['owner'] ?? 'Gonca LÖK')),
            'due_date' => trim((string) ($_POST['due_date'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'open')),
        ]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Müdahale planı kaydedildi.'];
    }
    header('Location: admin.php?action=student-risk&id=' . $sid);
    exit;
}

if ($action === 'intervention-status') {
    platform_check_csrf();
    $sid = (int) ($_GET['student_id'] ?? 0);
    $id = (int) ($_GET['id'] ?? 0);
    $status = (string) ($_GET['to'] ?? 'done');
    if ($id > 0) { platform_set_intervention_status($id, $status); }
    header('Location: admin.php?action=student-risk&id=' . $sid);
    exit;
}

if ($action === 'intervention-delete') {
    platform_check_csrf();
    $sid = (int) ($_GET['student_id'] ?? 0);
    if ((int) ($_GET['id'] ?? 0) > 0) { platform_delete_intervention((int) $_GET['id']); }
    header('Location: admin.php?action=student-risk&id=' . $sid);
    exit;
}

// ---- Faz 4: portal bağlantıları ve bildirimler ----

if ($action === 'portal-token-issue' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    $role = (string) ($_POST['role'] ?? 'student');
    if ($sid > 0 && in_array($role, ['student', 'guardian'], true)) {
        $issued = platform_portal_issue_token($sid, $role);
        $_SESSION['admin_portal_link'] = [
            'role' => $role,
            'url' => $issued['url'],
            'expires_at' => $issued['expires_at'],
        ];
        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'msg' => portal_role_label($role) . ' portalı bağlantısı oluşturuldu. Bağlantıyı kopyalayıp gönderin.',
        ];
        platform_admin_notify(
            $sid,
            'portal_link_' . $role,
            'Gonca LÖK — ' . portal_role_label($role) . ' Portalı Bağlantınız',
            "Merhaba,\n\nKişisel koçluk portalınıza aşağıdaki bağlantıdan erişebilirsiniz. Bağlantı " . substr($issued['expires_at'], 0, 10) . " tarihine kadar geçerlidir.\n\n" . $issued['url'] . "\n\nBağlantıyı kimseyle paylaşmayın.\n\nGonca LÖK",
            $role
        );
    }
    header('Location: admin.php?action=student-view&id=' . $sid);
    exit;
}

if ($action === 'portal-token-revoke' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $sid = (int) ($_POST['student_id'] ?? 0);
    $role = (string) ($_POST['role'] ?? 'student');
    if ($sid > 0 && in_array($role, ['student', 'guardian'], true)) {
        platform_portal_revoke_token($sid, $role);
        unset($_SESSION['admin_portal_link']);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => portal_role_label($role) . ' portalı bağlantısı iptal edildi.'];
    }
    header('Location: admin.php?action=student-view&id=' . $sid);
    exit;
}

// ---- Faz 5: PDF raporlar ----

if ($action === 'report-pdf') {
    platform_check_csrf();
    $sid = (int) ($_GET['id'] ?? 0);
    $scope = (string) ($_GET['scope'] ?? 'coach') === 'shared' ? 'shared' : 'coach';
    [$from, $to] = platform_report_period();
    $pdf = new PlatformPdf();
    $filename = platform_write_student_report($pdf, $sid, $scope, $from, $to);
    platform_pdf_download($filename, $pdf->output());
}

if ($action === 'report-overview-pdf') {
    platform_check_csrf();
    $pdf = new PlatformPdf();
    platform_pdf_download(platform_write_overview_report($pdf), $pdf->output());
}

// ---- Silme işlemi ----
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && (int) $_POST['id'] > 0) {
    platform_check_csrf();
    db_delete_post((int) $_POST['id']);
    $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Yazı silindi.'];
    header('Location: admin.php?action=list');
    exit;
}

// ---- Yazı kaydetme (create / update) ----
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $slug = isset($_POST['slug']) ? trim((string) $_POST['slug']) : '';
    $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
    $content = isset($_POST['content']) ? (string) $_POST['content'] : '';
    $category = isset($_POST['category']) ? trim((string) $_POST['category']) : 'Öğrenci Koçluğu';
    $image = isset($_POST['image']) ? trim((string) $_POST['image']) : '';
    $status = isset($_POST['status']) ? (string) $_POST['status'] : 'draft';
    $reading_time = isset($_POST['reading_time']) ? (int) $_POST['reading_time'] : 0;

    // Slug doğrula (a-z, 0-9, tire)
    if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Slug geçersiz. Yalnızca küçük harf, rakam ve tire kullanın.'];
        header('Location: admin.php?action=edit' . ($id > 0 ? '&id=' . $id : ''));
        exit;
    }
    if ($title === '') {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Başlık zorunludur.'];
        header('Location: admin.php?action=edit' . ($id > 0 ? '&id=' . $id : ''));
        exit;
    }

    // İçeriği izin listesine göre temizle (stored-XSS koruması)
    $content = blog_sanitize_html($content);

    // ---- Görsel yükleme (dosya) ----
    if (isset($_FILES['image_file']) && (int) $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/assets/images/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
        $maxBytes = 4 * 1024 * 1024; // 4 MB (1600x500 yeniden işlenecek)
        $ext = strtolower(pathinfo((string) $_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $allowedMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $_FILES['image_file']['tmp_name']) : '';
        if ($finfo) { finfo_close($finfo); }
        $size = (int) $_FILES['image_file']['size'];
        $imageOk = $size > 0 && $size <= $maxBytes && isset($allowedMime[$ext]) && $mime === $allowedMime[$ext];
        if ($imageOk) {
            // Blog standardı: 1600x500 (3.2:1) — merkezden kırpılıp yeniden kodlanır
            $tmp = (string) $_FILES['image_file']['tmp_name'];
            $src = null;
            if ($mime === 'image/jpeg') { $src = @imagecreatefromjpeg($tmp); }
            elseif ($mime === 'image/png') { $src = @imagecreatefrompng($tmp); }
            elseif ($mime === 'image/webp') { $src = @imagecreatefromwebp($tmp); }
            $imageOk = $src !== false && $src !== null;
            if ($imageOk) {
                $sw = imagesx($src); $sh = imagesy($src);
                $targetRatio = 1600 / 500;
                $srcRatio = $sw / $sh;
                if ($srcRatio > $targetRatio) { // çok geniş: yanları kırp
                    $cropW = (int) round($sh * $targetRatio);
                    $cropH = $sh;
                    $cropX = (int) (($sw - $cropW) / 2);
                    $cropY = 0;
                } else { // çok uzun: üst/alt kırp
                    $cropW = $sw;
                    $cropH = (int) round($sw / $targetRatio);
                    $cropX = 0;
                    $cropY = (int) (($sh - $cropH) / 2);
                }
                $dst = imagecreatetruecolor(1600, 500);
                if ($dst) {
                    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, 1600, 500, $cropW, $cropH);
                    $newName = 'blog-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.webp';
                    imagewebp($dst, $uploadDir . $newName, 82);
                    imagedestroy($dst);
                    $image = $newName;
                }
                imagedestroy($src);
            }
        }
        if (!$imageOk) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Görsel yüklenemedi. Geçerli bir JPG/PNG/WebP (en fazla 4 MB) seçin. Görsel otomatik olarak 1600×500 yapılır.'];
            header('Location: admin.php?action=edit' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
    }

    if ($id > 0) {
        db_update_post($id, $slug, $title, $content, $category, $image, $status, $reading_time);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Yazı güncellendi.'];
    } else {
        db_create_post($slug, $title, $content, $category, $image, $status, $reading_time);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Yeni yazı oluşturuldu.'];
    }

    header('Location: admin.php?action=list');
    exit;
}

// ---- Randevu uygunluğu (herkese açık / public) ----
if ($action === 'appointments-check-slot') {
    header('Content-Type: application/json');
    $apptDate = trim((string) ($_REQUEST['appointment_date'] ?? ''));
    $apptTime = trim((string) ($_REQUEST['appointment_time'] ?? ''));

    try {
        [$date, $time] = db_normalize_slot($apptDate, $apptTime);
        $conflict = db_find_slot_conflict($date, $time);
        echo json_encode([
            'ok' => true,
            'available' => $conflict === null,
            'date' => $date,
            'time' => substr($time, 0, 5),
            'source' => $conflict['source'] ?? null,
            'message' => $conflict === null ? 'Bu saat müsait.' : $conflict['message'],
        ]);
    } catch (AppointmentValidationException $e) {
        echo json_encode(['ok' => false, 'available' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'available' => false, 'error' => 'Uygunluk kontrolü şu anda yapılamıyor.']);
    }
    exit;
}

// ---- Dolu randevu saatleri listesi (herkese açık / public) ----
if ($action === 'appointments-public-list') {
    header('Content-Type: application/json');
    try {
        echo json_encode(['ok' => true, 'appointments' => db_get_public_appointments()]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'appointments' => [], 'error' => 'Randevu tablosu şu anda yüklenemiyor.']);
    }
    exit;
}

// ---- Günlük saat tablosu (herkese açık / public, admin tarafından da kullanılır) ----
if ($action === 'appointments-slots') {
    header('Content-Type: application/json');
    $slotDate = trim((string) ($_REQUEST['date'] ?? date('Y-m-d')));
    try {
        echo json_encode([
            'ok' => true,
            'date' => $slotDate,
            'slots' => db_get_day_slot_status($slotDate),
        ]);
    } catch (AppointmentValidationException $e) {
        echo json_encode(['ok' => false, 'slots' => [], 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'slots' => [], 'error' => 'Saat tablosu yüklenemedi.']);
    }
    exit;
}

// ---- Haftalık saat tablosu (herkese açık / public, admin tarafından da kullanılır) ----
if ($action === 'appointments-week') {
    header('Content-Type: application/json');
    $slotDate = trim((string) ($_REQUEST['date'] ?? date('Y-m-d')));
    try {
        $week = db_get_week_slot_status($slotDate);
        echo json_encode(array_merge(['ok' => true], $week));
    } catch (AppointmentValidationException $e) {
        echo json_encode(['ok' => false, 'days' => [], 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'days' => [], 'error' => 'Haftalık tablo yüklenemedi.']);
    }
    exit;
}

// ---- Admin: meşgul saat aç/kapat ----
if ($action === 'appointments-busy-toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    header('Content-Type: application/json');
    $slotDate = trim((string) ($_POST['date'] ?? ''));
    $slotTime = trim((string) ($_POST['time'] ?? ''));
    $mode = trim((string) ($_POST['mode'] ?? 'block'));
    $reason = trim((string) ($_POST['reason'] ?? ''));

    try {
        [$date, $time] = db_normalize_slot($slotDate, $slotTime);

        if ($mode === 'block') {
            $appointment = db_find_appointment_conflict($date, $time);
            if ($appointment) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Bu saatte zaten bir randevu var. Önce randevuyu iptal edin veya silin.']);
                exit;
            }
            db_set_busy_slot($date, $time, $reason);
            echo json_encode(['ok' => true, 'mode' => 'block', 'message' => 'Saat meşgul olarak işaretlendi.']);
        } elseif ($mode === 'unblock') {
            db_delete_busy_slot($date, $time);
            echo json_encode(['ok' => true, 'mode' => 'unblock', 'message' => 'Saat serbest bırakıldı.']);
        } else {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Geçersiz işlem.']);
        }
    } catch (AppointmentValidationException $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'İşlem kaydedilemedi.']);
    }
    exit;
}

// ---- Randevu oluşturma (herkese açık / public) ----
if ($action === 'appointments-public-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Hız sınırlama: IP başına saatte 8 randevu talebi
    if (!rate_limit_hit('public_appointment', client_ip(), 8, 3600)) {
        app_log('INFO', 'public_appointment rate limited', ['ip' => client_ip()]);
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Çok fazla deneme yapıldı. Lütfen bir saat sonra tekrar deneyin.']);
        exit;
    }
    $apptName = trim((string) ($_POST['name'] ?? ''));
    $apptPhone = trim((string) ($_POST['phone'] ?? ''));
    $apptEmail = trim((string) ($_POST['email'] ?? ''));
    $apptCoaching = trim((string) ($_POST['coaching_type'] ?? 'Öğrenci Koçluğu'));
    $apptDate = trim((string) ($_POST['appointment_date'] ?? ''));
    $apptTime = trim((string) ($_POST['appointment_time'] ?? ''));
    $apptNotes = trim((string) ($_POST['notes'] ?? ''));

    if ($apptName === '' || $apptPhone === '' || $apptEmail === '' || $apptDate === '' || $apptTime === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Lütfen zorunlu alanların tamamını doldurun.']);
        exit;
    }
    if (mb_strlen($apptName) > 255 || mb_strlen($apptNotes) > 2000) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Girdiğiniz metin çok uzun.']);
        exit;
    }
    if (!db_valid_phone($apptPhone)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Lütfen geçerli bir telefon numarası yazın.']);
        exit;
    }
    if (!db_valid_email($apptEmail)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Lütfen geçerli bir e-posta adresi yazın.']);
        exit;
    }

    if ($apptDate < date('Y-m-d') || ($apptDate === date('Y-m-d') && $apptTime <= date('H:i'))) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Geçmiş bir saat için randevu alınamaz.']);
        exit;
    }

    try {
        $apptId = db_create_appointment($apptName, $apptPhone, $apptEmail, $apptCoaching, $apptDate, $apptTime, 'pending', $apptNotes);
        // Talep onayı e-postası (MAIL_ENABLED=true ise gönderilir; değilse kayda alınır)
        $confirmBody = "Merhaba {$apptName},\n\n"
            . "Randevu talebiniz alındı.\n\n"
            . "Tarih: {$apptDate}\nSaat: " . substr($apptTime, 0, 5) . "\nTür: {$apptCoaching}\n\n"
            . "Talebiniz Gonca Hanım'a iletildi; 24 saat içinde (genellikle çok daha önce) size {$apptPhone} numarasından veya bu adresten dönüş yapılır.\n\n"
            . "Bu e-posta üyeliğiniz kapsamında otomatik gönderilmiştir. Herhangi bir sorunuz olursa yanıtlayabilirsiniz.\n\nGonca LÖK";
        $mailResult = platform_send_mail($apptEmail, 'Randevu talebiniz alındı — Gonca LÖK', $confirmBody);
        platform_log_notification(null, 'appointment_confirm', $apptEmail, 'Randevu talebiniz alındı — Gonca LÖK', $confirmBody, $mailResult['status'], $mailResult['error']);
        echo json_encode(['ok' => true, 'id' => $apptId, 'message' => 'Randevu talebiniz alındı. Onay e-postası gönderildi (varsa).']);
    } catch (AppointmentConflictException $e) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    } catch (AppointmentValidationException $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Randevu talebiniz kaydedilemedi. Lütfen tekrar deneyin.']);
    }
    exit;
}

// ---- Admin görünümleri ----
if ($action === 'login') {
    // Giriş formu
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Giriş | Gonca LÖK Panel</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body">
      <a class="skip-link" href="#icerik">İçeriğe geç</a>
      <main id="icerik" class="admin-login-wrap">
        <div class="card admin-login-card">
          <span class="eyebrow text-center" style="display:block;">Gonca LÖK</span>
          <h2 class="mt-8 text-center">Panel Girişi</h2>
          <p class="mt-8 text-center" style="font-size:14px;color:var(--gray);margin-bottom:24px;">Sitenizi yönetmek için giriş yapın</p>
          <?php if (isset($_GET['reason']) && $_GET['reason'] === 'idle'): ?>
            <div class="admin-alert-error" role="status">Oturumunuzun süresi doldu. Lütfen tekrar giriş yapın.</div>
          <?php endif; ?>
          <?php if (!empty($adminFlash) && ($adminFlash['type'] ?? '') === 'success'): ?>
            <div class="admin-alert-error" role="status" style="background:#e8f5ec;color:#1e7e34;"><?php echo htmlspecialchars((string) $adminFlash['msg'], ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <?php if (isset($loginError)): ?>
            <div class="admin-alert-error" role="alert"><?php echo icon("x", 16); ?> <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <form method="post" action="admin.php?action=login" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
            <div class="form-field" style="margin-bottom:14px;">
              <label for="email">E-posta</label>
              <input type="email" id="email" name="email" required autocomplete="username" style="min-height:48px;">
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="password">Şifre</label>
              <input type="password" id="password" name="password" required autocomplete="current-password" style="min-height:48px;">
            </div>
            <button type="submit" class="btn btn-primary btn-full btn-lg">Giriş Yap</button>
          </form>
        </div>
      </main>
    </body>
    </html>
    <?php
} elseif ($action === 'dashboard') {
    $stats = db_get_post_stats();
    $totalCount = $stats['total']; $publishedCount = $stats['published']; $draftCount = $stats['draft']; $totalReading = $stats['reading'];
    $recentPosts = db_get_all_posts(null, 5, 0);
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Admin | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div>
              <span class="eyebrow">Yönetim Paneli</span>
              <h1>Genel Bakış</h1>
            </div>
            <a href="admin.php?action=edit" class="btn btn-primary">＋ Yeni Yazı Ekle</a>
          </div>

          <div class="admin-stats">
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("pencil", 14); ?></div>
              <div class="stat-num"><?php echo $totalCount; ?></div>
              <div class="stat-lbl">Toplam Yazı</div>
            </div>
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("check-circle", 16); ?></div>
              <div class="stat-num"><?php echo $publishedCount; ?></div>
              <div class="stat-lbl">Yayınlanan</div>
            </div>
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("file-text", 26); ?></div>
              <div class="stat-num"><?php echo $draftCount; ?></div>
              <div class="stat-lbl">Taslak</div>
            </div>
            <div class="card admin-stat">
              <div class="card-icon">⏱</div>
              <div class="stat-num"><?php echo $totalReading; ?></div>
              <div class="stat-lbl">Toplam Okuma (dk)</div>
            </div>
          </div>

          <?php $overview = platform_get_overview(); ?>
          <h4 class="mt-32 mb-16"><?php echo icon("graduation", 18); ?> Koçluk Platformu</h4>
          <div class="admin-stats">
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("users", 16); ?></div>
              <div class="stat-num"><?php echo (int) $overview['active_students']; ?></div>
              <div class="stat-lbl">Aktif Öğrenci</div>
            </div>
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("alert", 16); ?>️</div>
              <div class="stat-num"><?php echo (int) $overview['risk_students']; ?></div>
              <div class="stat-lbl">Risk Altında</div>
            </div>
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("calendar-month", 16); ?>️</div>
              <div class="stat-num"><?php echo (int) $overview['upcoming_sessions']; ?></div>
              <div class="stat-lbl">Yaklaşan Seans</div>
            </div>
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("check-circle", 16); ?></div>
              <div class="stat-num"><?php echo (int) $overview['open_actions']; ?></div>
              <div class="stat-lbl">Açık Ödev</div>
            </div>
          </div>

          <?php $openRiskCount = platform_get_open_risk_count(); if ($openRiskCount > 0): ?>
            <div class="admin-risk-alert mt-16">
              <span><?php echo icon("alert", 16); ?>️ <strong><?php echo $openRiskCount; ?></strong> açık risk uyarısı var. Öğrenci dosyalarındaki “Risk Takibi” sekmesinden inceleyin.</span>
              <a href="admin.php?action=students" class="btn btn-secondary btn-mini">Öğrencileri görüntüle</a>
            </div>
          <?php endif; ?>

          <div class="grid-2 mt-32" style="align-items:start;">
            <div class="card" style="padding:24px;">
              <h4 class="mb-16"><?php echo icon("file-text", 16); ?> Son Yazılar</h4>
              <?php foreach ($recentPosts as $p): ?>
              <div class="admin-recent-item">
                <div>
                  <div class="admin-post-title" style="font-size:15px;"><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="admin-post-slug">/<?php echo htmlspecialchars($p['slug'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                  <span class="status-pill <?php echo $p['status'] === 'published' ? 'published' : 'draft'; ?>"><?php echo $p['status'] === 'published' ? 'Yayınlandı' : 'Taslak'; ?></span>
                  <a href="admin.php?action=edit&id=<?php echo (int) $p['id']; ?>" class="btn btn-secondary" style="font-size:12px;padding:8px 14px;"><?php echo icon("pencil", 14); ?></a>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <div>
              <div class="card" style="padding:24px;">
                <h4 class="mb-16"><?php echo icon("motivation", 16); ?> Hızlı İşlemler</h4>
                <div class="btn-group" style="flex-direction:column;align-items:stretch;">
                  <a href="admin.php?action=student-edit" class="btn btn-primary btn-full">＋ Yeni Öğrenci Ekle</a>
                  <a href="admin.php?action=students" class="btn btn-secondary btn-full"><?php echo icon("users", 16); ?> Öğrencileri Yönet</a>
                  <a href="admin.php?action=list" class="btn btn-secondary btn-full"><?php echo icon("pencil", 14); ?> Tüm Yazıları Yönet</a>
                  <a href="blog.php" target="_blank" class="btn btn-white btn-full"><?php echo icon("globe", 16); ?> Blogu Site Önizle</a>
                </div>
              </div>
              <div class="card mt-16" style="padding:24px;">
                <h4 class="mb-16">ℹ️ Bilgi</h4>
                <p style="font-size:14px;color:var(--gray);">Yeni yayınlanan haftalık makale süreci: Admin panelinden yazı oluştur → yayınla → blog listesinde görünür.</p>
              </div>
            </div>
          </div>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'list') {
    $stats = db_get_post_stats();
    $totalCount = $stats['total']; $publishedCount = $stats['published']; $draftCount = $stats['draft']; $totalReading = $stats['reading'];
    $perPage = 20;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $totalPages = max(1, (int) ceil($totalCount / $perPage));
    if ($page > $totalPages) { $page = $totalPages; }
    $posts = db_get_all_posts(null, $perPage, ($page - 1) * $perPage);
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Admin | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div>
              <span class="eyebrow">Yönetim Paneli</span>
              <h1>Blog Yazıları</h1>
            </div>
            <a href="admin.php?action=edit" class="btn btn-primary">＋ Yeni Yazı Ekle</a>
          </div>

          <div class="admin-stats">
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("pencil", 14); ?></div>
              <div class="stat-num"><?php echo $totalCount; ?></div>
              <div class="stat-lbl">Toplam Yazı</div>
            </div>
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("check-circle", 16); ?></div>
              <div class="stat-num"><?php echo $publishedCount; ?></div>
              <div class="stat-lbl">Yayınlanan</div>
            </div>
            <div class="card admin-stat">
              <div class="card-icon"><?php echo icon("file-text", 26); ?></div>
              <div class="stat-num"><?php echo $draftCount; ?></div>
              <div class="stat-lbl">Taslak</div>
            </div>
            <div class="card admin-stat">
              <div class="card-icon">⏱</div>
              <div class="stat-num"><?php echo $totalReading; ?></div>
              <div class="stat-lbl">Toplam Okuma (dk)</div>
            </div>
          </div>

          <div class="card admin-card mt-32" style="padding:0;overflow:hidden;">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Başlık</th>
                    <th>Kategori</th>
                    <th>Durum</th>
                    <th>Okuma</th>
                    <th>Tarih</th>
                    <th style="text-align:right;">İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($posts as $post): ?>
                  <tr>
                    <td>
                      <div class="admin-post-title"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <div class="admin-post-slug">/<?php echo htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($post['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="status-pill <?php echo $post['status'] === 'published' ? 'published' : 'draft'; ?>"><?php echo $post['status'] === 'published' ? 'Yayınlandı' : 'Taslak'; ?></span></td>
                    <td><?php echo (int) $post['reading_time']; ?> dk</td>
                    <td><?php echo date('d.m.Y', strtotime($post['created_at'])); ?></td>
                    <td class="admin-actions">
                      <a href="admin.php?action=edit&id=<?php echo (int) $post['id']; ?>" class="btn btn-secondary" style="font-size:12px;padding:8px 14px;margin-left:8px;"><?php echo icon("pencil", 14); ?> Düzenle</a>
                      <form method="post" action="admin.php?action=delete" style="display:inline;" onsubmit="return confirm('Yazıyı silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
                        <button type="submit" class="btn btn-wa" style="font-size:12px;padding:8px 14px;"><?php echo icon("trash", 14); ?> Sil</button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <?php if ($totalPages > 1): ?>
          <nav class="mt-24 text-center" aria-label="Sayfalama">
            <?php for ($pNo = 1; $pNo <= $totalPages; $pNo++): ?>
              <a class="btn <?php echo $pNo === $page ? 'btn-primary' : 'btn-secondary'; ?> btn-mini" href="admin.php?action=list&amp;page=<?php echo $pNo; ?>"><?php echo $pNo; ?></a>
            <?php endfor; ?>
          </nav>
          <?php endif; ?>
          <div class="mt-24 text-center"><a href="admin.php?action=edit" class="btn btn-primary">＋ Yeni Yazı Ekle</a></div>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'edit') {
    $postId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $post = $postId > 0 ? db_get_post_by_id($postId) : null;
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Admin Yazı Editörü | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container" style="max-width:900px;">
          <div class="admin-page-head">
            <div>
              <span class="eyebrow">Yönetim Paneli</span>
              <h1><?php echo $post ? 'Yazıyı Düzenle' : 'Yeni Yazı Ekle'; ?></h1>
            </div>
            <a href="admin.php?action=list" class="btn btn-secondary">← Listeye Dön</a>
          </div>

          <form method="post" action="admin.php?action=save<?php echo $post ? '&id=' . (int) $post['id'] : ''; ?>" class="admin-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
            <?php if ($post): ?>
              <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
            <?php endif; ?>
            <div class="admin-form-row">
              <div class="form-field">
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" required placeholder="ornek-yazi" value="<?php echo $post ? htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') : ''; ?>">
              </div>
              <div class="form-field">
                <label for="title">Başlık</label>
                <input type="text" id="title" name="title" required placeholder="Yeni blog yazısı" value="<?php echo $post ? htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') : ''; ?>">
              </div>
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="category">Kategori</label>
              <select id="category" name="category">
                <option value="Öğrenci Koçluğu" <?php echo (!$post || $post['category'] === 'Öğrenci Koçluğu') ? 'selected' : ''; ?>>Öğrenci Koçluğu</option>
                <option value="Kariyer Koçluğu" <?php echo $post && $post['category'] === 'Kariyer Koçluğu' ? 'selected' : ''; ?>>Kariyer Koçluğu</option>
                <option value="Koçluk Süreci" <?php echo $post && $post['category'] === 'Koçluk Süreci' ? 'selected' : ''; ?>>Koçluk Süreci</option>
              </select>
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="image">Görseli Yükle (opsiyonel)</label>
              <input type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/webp">
              <small class="admin-muted">Görsel otomatik olarak 1600×500 (blog standardı) kırpılır ve WebP kaydedilir.</small>
              <input type="text" id="image" name="image" placeholder="assets/images/xxx.webp" value="<?php echo $post && $post['image'] ? htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="reading_time">Okuma süresi (dk)</label>
              <input type="number" id="reading_time" name="reading_time" min="0" value="<?php echo $post ? (int) $post['reading_time'] : 3; ?>">
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="status">Durum</label>
              <select id="status" name="status">
                <option value="draft" <?php echo (!$post || $post['status'] === 'draft') ? 'selected' : ''; ?>>Taslak</option>
                <option value="published" <?php echo $post && $post['status'] === 'published' ? 'selected' : ''; ?>>Yayınlandı</option>
              </select>
            </div>
            <div class="form-field" style="margin-bottom:24px;">
              <label for="content">Makale İçeriği (HTML)</label>
              <textarea id="content" name="content" rows="14"><?php echo $post ? htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
            </div>
            <div class="admin-form-actions" style="justify-content:space-between;">
              <?php if ($post): ?>
                <a href="blog.php?slug=<?php echo urlencode($post['slug']); ?>" target="_blank" class="btn btn-secondary"><?php echo icon("eye", 14); ?> Önizle</a>
              <?php else: ?>
                <span></span>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary btn-lg">Kaydet</button>
            </div>
          </form>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'appointments') {
    $statusFilter = trim((string) ($_GET['status'] ?? ''));
    if (!in_array($statusFilter, ['pending', 'confirmed', 'cancelled'], true)) {
        $statusFilter = '';
    }
    $perPage = 25;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $totalAppts = db_count_appointments($statusFilter !== '' ? $statusFilter : null);
    $totalPages = max(1, (int) ceil($totalAppts / $perPage));
    if ($page > $totalPages) { $page = $totalPages; }
    $appointments = db_get_appointments($statusFilter !== '' ? $statusFilter : null, null, $perPage, ($page - 1) * $perPage);
    $studentMap = [];
    foreach (platform_get_students() as $st) {
        $studentMap[(int) $st['id']] = $st['student_code'] . ' — ' . $st['first_name'] . ' ' . $st['last_name'];
    }
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Admin Randevuları | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div>
              <span class="eyebrow">Yönetim Paneli</span>
              <h1>Randevular</h1>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <a href="admin.php?action=appointments-schedule" class="btn btn-secondary"><?php echo icon("clock", 14); ?> Dolu Saatleri Yönet</a>
              <a href="admin.php?action=student-edit" class="btn btn-secondary">＋ Yeni Öğrenci</a>
              <a href="admin.php?action=appointments-edit" class="btn btn-primary">＋ Yeni Randevu Ekle</a>
            </div>
          </div>

          <form method="get" action="admin.php" class="admin-filter-bar">
            <input type="hidden" name="action" value="appointments">
            <label for="appt_status_filter">Durum:</label>
            <select id="appt_status_filter" name="status" onchange="this.form.submit()">
              <option value="">Tümü</option>
              <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Beklemede</option>
              <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Onaylandı</option>
              <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>İptal</option>
            </select>
            <span class="admin-filter-count"><?php echo $totalAppts; ?> kayıt</span>
          </form>
          <?php if ($totalPages > 1): ?>
          <nav class="mt-16" aria-label="Sayfalama">
            <?php for ($pNo = 1; $pNo <= $totalPages; $pNo++): ?>
              <a class="btn <?php echo $pNo === $page ? 'btn-primary' : 'btn-secondary'; ?> btn-mini" href="admin.php?action=appointments&amp;status=<?php echo rawurlencode($statusFilter); ?>&amp;page=<?php echo $pNo; ?>"><?php echo $pNo; ?></a>
            <?php endfor; ?>
          </nav>
          <?php endif; ?>

          <div class="card admin-card mt-16" style="padding:0;overflow:hidden;">
            <div class="admin-table-wrap">
              <table class="admin-table admin-table-appointments">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Ad Soyad</th>
                    <th>Öğrenci Kaydı</th>
                    <th>Telefon</th>
                    <th>E-posta</th>
                    <th>Koçluk Tipi</th>
                    <th>Tarih</th>
                    <th>Saat</th>
                    <th>Durum</th>
                    <th>Notlar</th>
                    <th>Oluşturulma</th>
                    <th style="text-align:right;">İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$appointments): ?>
                    <tr><td colspan="12" class="admin-table-empty">Henüz randevu kaydı yok.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($appointments as $appt): ?>
                  <tr>
                    <td class="admin-post-slug">#<?php echo (int) $appt['id']; ?></td>
                    <td><div class="admin-post-title"><?php echo htmlspecialchars($appt['name'], ENT_QUOTES, 'UTF-8'); ?></div></td>
                    <td>
                      <?php
                      $linkedId = platform_get_appointment_student_id((int) $appt['id']);
                      if ($linkedId && isset($studentMap[$linkedId])): ?>
                        <a href="admin.php?action=student-view&id=<?php echo $linkedId; ?>" class="admin-link"><?php echo platform_e($studentMap[$linkedId]); ?></a>
                      <?php else: ?>
                        <span class="admin-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($appt['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $appt['email'] ? htmlspecialchars($appt['email'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                    <td><?php echo htmlspecialchars($appt['coaching_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $appt['appointment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(substr((string) $appt['appointment_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="status-pill <?php echo $appt['status'] === 'confirmed' ? 'published' : ($appt['status'] === 'cancelled' ? 'draft' : 'pending'); ?>"><?php echo $appt['status'] === 'confirmed' ? 'Onaylandı' : ($appt['status'] === 'cancelled' ? 'İptal' : 'Beklemede'); ?></span></td>
                    <td class="admin-table-notes"><?php echo $appt['notes'] ? nl2br(htmlspecialchars($appt['notes'], ENT_QUOTES, 'UTF-8')) : '—'; ?></td>
                    <td class="admin-post-slug"><?php echo htmlspecialchars(substr((string) $appt['created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="admin-actions">
                      <a href="admin.php?action=appointments-edit&id=<?php echo (int) $appt['id']; ?>" class="btn btn-secondary" style="font-size:12px;padding:8px 14px;margin-left:8px;"><?php echo icon("pencil", 14); ?> Düzenle</a>
                      <form method="post" action="admin.php?action=appointments-delete" style="display:inline;" onsubmit="return confirm('Randevu silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $appt['id']; ?>">
                        <button type="submit" class="btn btn-wa" style="font-size:12px;padding:8px 14px;"><?php echo icon("trash", 14); ?> Sil</button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'appointments-schedule') {
    $scheduleDate = trim((string) ($_GET['date'] ?? date('Y-m-d')));
    if (!DateTime::createFromFormat('!Y-m-d', $scheduleDate)) {
        $scheduleDate = date('Y-m-d');
    }
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Admin Dolu Saatler | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div>
              <span class="eyebrow">Yönetim Paneli</span>
              <h1>Dolu Saatler</h1>
            </div>
            <a href="admin.php?action=appointments" class="btn btn-secondary">← Randevulara Dön</a>
          </div>

          <div class="admin-form mt-32">
            <p class="admin-slot-note">Seanslar 1 saattir ve yalnızca saat başında başlar. Çalışma saatleri 09:00 - 19:00 arasıdır; son seans 18:00'da başlar. Bir hücreye tıklayarak o saati <strong>Dolu</strong> yapabilir veya tekrar tıklayarak serbest bırakabilirsiniz. İşaretlediğiniz saatler kullanıcıya <strong>Dolu</strong> olarak görünür.</p>

            <div class="admin-slot-controls">
              <div class="form-field" style="margin-bottom:0;flex:1;">
                <label for="admin_slot_reason">Sebep (isteğe bağlı)</label>
                <input type="text" id="admin_slot_reason" placeholder="Örn: Kişisel görüşme" style="min-height:48px;" maxlength="255">
                <input type="hidden" id="admin_csrf_token" value="<?php echo platform_e(platform_csrf_token()); ?>">
              </div>
            </div>

            <div class="slot-feedback admin-slot-feedback" id="adminSlotFeedback" role="status" aria-live="polite"></div>

            <div class="week-nav">
              <button type="button" class="btn btn-secondary" id="adminWeekPrev">← Önceki Hafta</button>
              <strong class="week-label" id="adminWeekLabel">Hafta yükleniyor...</strong>
              <button type="button" class="btn btn-secondary" id="adminWeekToday">Bu Hafta</button>
              <button type="button" class="btn btn-secondary" id="adminWeekNext">Sonraki Hafta →</button>
            </div>

            <input type="hidden" id="admin_week_anchor" value="<?php echo htmlspecialchars($scheduleDate, ENT_QUOTES, 'UTF-8'); ?>" data-today="<?php echo date('Y-m-d'); ?>">

            <div class="card public-appointments-card" style="padding:0;overflow:hidden;">
              <div class="public-table-wrap">
                <table class="week-table" id="adminWeekTable">
                  <thead id="adminWeekTableHead">
                    <tr><th colspan="7" class="public-table-empty">Hafta yükleniyor...</th></tr>
                  </thead>
                  <tbody id="adminWeekTableBody">
                    <tr><td colspan="7" class="public-table-empty">Hafta yükleniyor...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p class="week-legend">
              <span><i class="week-legend-dot available"></i> Müsait (tıklayınca Dolu olur)</span>
              <span><i class="week-legend-dot busy"></i> Dolu / admin kapattı (tıklayınca serbest olur)</span>
              <span><i class="week-legend-dot booked"></i> Dolu (randevu var)</span>
              <span><i class="week-legend-dot past"></i> Geçmiş</span>
            </p>
          </div>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'appointments-edit') {
    $apptId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $appt = null;
    if ($apptId > 0) { $appt = db_get_appointment($apptId); }
    $allStudents = platform_get_students();
    $linkedStudentId = $appt ? platform_get_appointment_student_id($apptId) : 0;
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Admin Randevu Editörü | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container" style="max-width:900px;">
          <div class="admin-page-head">
            <div>
              <span class="eyebrow">Yönetim Paneli</span>
              <h1><?php echo $appt ? 'Randevuyu Düzenle' : 'Yeni Randevu Ekle'; ?></h1>
            </div>
            <a href="admin.php?action=appointments" class="btn btn-secondary">← Listeye Dön</a>
          </div>

          <form method="post" action="admin.php?action=appointments-save<?php echo $apptId > 0 ? '&id=' . (int) $apptId : ''; ?>" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
            <?php if ($apptId > 0): ?>
              <input type="hidden" name="id" value="<?php echo (int) $apptId; ?>">
            <?php endif; ?>
            <div class="admin-form-row">
              <div class="form-field">
                <label for="appt_name">Ad Soyad *</label>
                <input type="text" id="appt_name" name="name" required style="min-height:48px;" value="<?php echo $appt ? htmlspecialchars($appt['name'], ENT_QUOTES, 'UTF-8') : ''; ?>">
              </div>
              <div class="form-field">
                <label for="appt_phone">Telefon *</label>
                <input type="text" id="appt_phone" name="phone" required style="min-height:48px;" value="<?php echo $appt ? htmlspecialchars($appt['phone'], ENT_QUOTES, 'UTF-8') : ''; ?>">
              </div>
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="appt_email">E-posta</label>
              <input type="email" id="appt_email" name="email" style="min-height:48px;" value="<?php echo $appt && $appt['email'] ? htmlspecialchars($appt['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="appt_coaching">Koçluk Tipi</label>
              <select id="appt_coaching" name="coaching_type">
                <option value="Öğrenci Koçluğu" <?php echo !$appt || $appt['coaching_type'] === 'Öğrenci Koçluğu' ? 'selected' : ''; ?>>Öğrenci Koçluğu</option>
                <option value="Kariyer Koçluğu" <?php echo $appt && $appt['coaching_type'] === 'Kariyer Koçluğu' ? 'selected' : ''; ?>>Kariyer Koçluğu</option>
                <option value="Koçluk Süreci" <?php echo $appt && $appt['coaching_type'] === 'Koçluk Süreci' ? 'selected' : ''; ?>>Koçluk Süreci</option>
              </select>
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="appt_student">Öğrenci Kaydı (opsiyonel)</label>
              <select id="appt_student" name="student_id">
                <option value="0">Henüz bağlı değil</option>
                <?php foreach ($allStudents as $st): ?>
                  <option value="<?php echo (int) $st['id']; ?>" <?php echo $linkedStudentId === (int) $st['id'] ? 'selected' : ''; ?>>
                    <?php echo platform_e($st['student_code'] . ' — ' . $st['first_name'] . ' ' . $st['last_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="admin-hint">Randevuyu öğrenci profiline bağlayınca seans notları o profilde açılır.</span>
            </div>
            <div class="admin-form-row">
              <div class="form-field" style="margin-bottom:18px;">
                <label for="appt_date">Randevu Tarihi *</label>
                <input type="date" id="appt_date" name="appointment_date" required style="min-height:48px;" value="<?php echo $appt ? htmlspecialchars((string) $appt['appointment_date'], ENT_QUOTES, 'UTF-8') : ''; ?>">
              </div>
              <div class="form-field" style="margin-bottom:18px;">
                <label for="appt_time">Randevu Saati *</label>
                <input type="time" id="appt_time" name="appointment_time" step="3600" required style="min-height:48px;" value="<?php echo $appt ? htmlspecialchars(substr((string) $appt['appointment_time'], 0, 5), ENT_QUOTES, 'UTF-8') : ''; ?>">
              </div>
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="appt_status">Durum</label>
              <select id="appt_status" name="status">
                <option value="pending" <?php echo !$appt || $appt['status'] === 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                <option value="confirmed" <?php echo $appt && $appt['status'] === 'confirmed' ? 'selected' : ''; ?>>Onaylandı</option>
                <option value="cancelled" <?php echo $appt && $appt['status'] === 'cancelled' ? 'selected' : ''; ?>>İptal</option>
              </select>
            </div>
            <div class="form-field" style="margin-bottom:24px;">
              <label for="appt_notes">Notlar</label>
              <textarea id="appt_notes" name="notes" rows="4" style="min-height:100px;"><?php echo $appt && $appt['notes'] ? htmlspecialchars($appt['notes'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
            </div>
            <div class="admin-form-actions">
              <a href="admin.php?action=appointments" class="btn btn-secondary">Listeye Dön</a>
              <button type="submit" class="btn btn-primary btn-lg">Randevu Kaydet</button>
            </div>
          </form>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'appointments-save') {
    platform_check_csrf();
    $apptId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $apptName = trim((string) ($_POST['name'] ?? ''));
    $apptPhone = trim((string) ($_POST['phone'] ?? ''));
    $apptEmail = trim((string) ($_POST['email'] ?? ''));
    $apptCoaching = trim((string) ($_POST['coaching_type'] ?? 'Öğrenci Koçluğu'));
    $apptDate = trim((string) ($_POST['appointment_date'] ?? ''));
    $apptTime = trim((string) ($_POST['appointment_time'] ?? ''));
    $apptStatus = trim((string) ($_POST['status'] ?? 'pending'));
    $apptNotes = trim((string) ($_POST['notes'] ?? ''));

    $errorRedirect = 'admin.php?action=appointments-edit' . ($apptId > 0 ? '&id=' . $apptId : '');

    if ($apptName === '' || $apptPhone === '' || $apptDate === '' || $apptTime === '') {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Lütfen zorunlu alanların tamamını doldurun.'];
        header('Location: ' . $errorRedirect);
        exit;
    }

    $apptStudentId = (int) ($_POST['student_id'] ?? 0);
    try {
        if ($apptId > 0) {
            db_update_appointment($apptId, $apptName, $apptPhone, $apptEmail, $apptCoaching, $apptDate, $apptTime, $apptStatus, $apptNotes);
            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Randevu güncellendi.'];
        } else {
            $apptId = db_create_appointment($apptName, $apptPhone, $apptEmail, $apptCoaching, $apptDate, $apptTime, $apptStatus, $apptNotes);
            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Yeni randevu oluşturuldu.'];
        }
        platform_link_appointment_student($apptId, $apptStudentId > 0 ? $apptStudentId : null);
    } catch (AppointmentConflictException $e) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
        header('Location: ' . $errorRedirect);
        exit;
    } catch (AppointmentValidationException $e) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
        header('Location: ' . $errorRedirect);
        exit;
    } catch (Throwable $e) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Randevu kaydedilemedi. Lütfen tekrar deneyin.'];
        header('Location: ' . $errorRedirect);
        exit;
    }

    header('Location: admin.php?action=appointments');
    exit;
} elseif ($action === 'appointments-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    platform_check_csrf();
    if (isset($_POST['id']) && (int) $_POST['id'] > 0) {
        db_delete_appointment((int) $_POST['id']);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Randevu silindi.'];
    }
    header('Location: admin.php?action=appointments');
    exit;
// ============================================================
// KOÇLUK PLATFORMU — görünümler
// ============================================================
} elseif ($action === 'students') {
    $statusFilter = trim((string) ($_GET['status'] ?? ''));
    if (!array_key_exists($statusFilter, platform_student_statuses())) { $statusFilter = ''; }
    $perPage = 20;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $totalStudents = platform_count_students($statusFilter);
    $totalPages = max(1, (int) ceil($totalStudents / $perPage));
    if ($page > $totalPages) { $page = $totalPages; }
    $students = platform_get_students($statusFilter, $perPage, ($page - 1) * $perPage);
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Öğrenciler | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div>
              <span class="eyebrow">Koçluk Platformu</span>
              <h1>Öğrenciler</h1>
            </div>
            <a href="admin.php?action=student-edit" class="btn btn-primary">＋ Yeni Öğrenci</a>
          </div>

          <form method="get" action="admin.php" class="admin-filter-bar">
            <input type="hidden" name="action" value="students">
            <label for="student_status_filter">Durum:</label>
            <select id="student_status_filter" name="status" onchange="this.form.submit()">
              <option value="">Tümü</option>
              <?php foreach (platform_student_statuses() as $key => $label): ?>
                <option value="<?php echo platform_e($key); ?>" <?php echo $statusFilter === $key ? 'selected' : ''; ?>><?php echo platform_e($label); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="admin-filter-count"><?php echo $totalStudents; ?> öğrenci</span>
          </form>
          <?php if ($totalPages > 1): ?>
            <nav class="mt-16" aria-label="Sayfalama">
              <?php for ($pNo = 1; $pNo <= $totalPages; $pNo++): ?>
                <a class="btn <?php echo $pNo === $page ? 'btn-primary' : 'btn-secondary'; ?> btn-mini" href="admin.php?action=students&amp;status=<?php echo rawurlencode($statusFilter); ?>&amp;page=<?php echo $pNo; ?>"><?php echo $pNo; ?></a>
              <?php endfor; ?>
            </nav>
          <?php endif; ?>

          <div class="card admin-card mt-16" style="padding:0;overflow:hidden;">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Kod</th><th>Ad Soyad</th><th>İletişim</th><th>Okul / Sınıf</th>
                    <th>Durum</th><th>Risk</th><th>Seans</th><th>Açık Ödev</th><th style="text-align:right;">İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$students): ?>
                    <tr><td colspan="9" class="admin-table-empty">Henüz öğrenci kaydı yok.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($students as $st): ?>
                  <tr>
                    <td class="admin-post-slug"><?php echo platform_e($st['student_code']); ?></td>
                    <td><a href="admin.php?action=student-view&id=<?php echo (int) $st['id']; ?>" class="admin-link strong"><?php echo platform_e($st['first_name'] . ' ' . $st['last_name']); ?></a></td>
                    <td><?php echo platform_e($st['phone']); ?><div class="admin-post-slug"><?php echo platform_e($st['email'] ?: '—'); ?></div></td>
                    <td><?php echo platform_e($st['school'] ?: '—'); ?><div class="admin-post-slug"><?php echo platform_e($st['grade_level'] ?: ''); ?></div></td>
                    <td><span class="admin-pill admin-pill-<?php echo platform_e($st['status']); ?>"><?php echo platform_e(platform_student_statuses()[$st['status']] ?? $st['status']); ?></span></td>
                    <td><span class="admin-pill admin-pill-risk-<?php echo platform_e($st['risk_level']); ?>"><?php echo platform_e(platform_risk_levels()[$st['risk_level']] ?? $st['risk_level']); ?></span></td>
                    <td><?php echo (int) $st['session_count']; ?></td>
                    <td><?php echo (int) $st['open_action_count']; ?></td>
                    <td class="admin-actions">
                      <a href="admin.php?action=student-view&id=<?php echo (int) $st['id']; ?>" class="btn btn-secondary btn-mini"><?php echo icon("eye", 14); ?> Dosya</a>
                      <a href="admin.php?action=student-edit&id=<?php echo (int) $st['id']; ?>" class="btn btn-secondary btn-mini"><?php echo icon("pencil", 14); ?> Düzenle</a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'student-edit') {
    $sid = (int) ($_GET['id'] ?? 0);
    $student = $sid > 0 ? platform_get_student($sid) : null;
    if ($sid > 0 && !$student) {
        http_response_code(404);
        echo 'Öğrenci bulunamadı.';
        exit;
    }
    $v = $student ?: [
        'first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '', 'birth_date' => '',
        'school' => '', 'grade_level' => '', 'coaching_type' => 'Öğrenci Koçluğu',
        'status' => 'active', 'risk_level' => 'low', 'guardian_name' => '',
        'guardian_phone' => '', 'guardian_email' => '', 'notes' => '', 'consent_at' => null,
    ];
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title><?php echo $student ? 'Öğrenci Düzenle' : 'Yeni Öğrenci'; ?> | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container" style="max-width:960px;">
          <div class="admin-page-head">
            <div>
              <span class="eyebrow">Koçluk Platformu</span>
              <h1><?php echo $student ? 'Öğrenci Düzenle' : 'Yeni Öğrenci Kaydı'; ?></h1>
            </div>
            <a href="<?php echo $student ? 'admin.php?action=student-view&id=' . $sid : 'admin.php?action=students'; ?>" class="btn btn-secondary">← Geri Dön</a>
          </div>

          <form method="post" action="admin.php?action=student-save" class="admin-form mt-24">
            <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
            <?php if ($sid > 0): ?><input type="hidden" name="id" value="<?php echo $sid; ?>"><?php endif; ?>

            <h4 class="mb-16">Kimlik ve İletişim</h4>
            <div class="admin-form-row">
              <div class="form-field"><label for="st_first">Ad *</label><input type="text" id="st_first" name="first_name" required value="<?php echo platform_e($v['first_name']); ?>"></div>
              <div class="form-field"><label for="st_last">Soyad *</label><input type="text" id="st_last" name="last_name" required value="<?php echo platform_e($v['last_name']); ?>"></div>
            </div>
            <div class="admin-form-row">
              <div class="form-field"><label for="st_phone">Telefon *</label><input type="text" id="st_phone" name="phone" required value="<?php echo platform_e($v['phone']); ?>"></div>
              <div class="form-field"><label for="st_email">E-posta</label><input type="email" id="st_email" name="email" value="<?php echo platform_e($v['email']); ?>"></div>
            </div>
            <div class="admin-form-row">
              <div class="form-field"><label for="st_birth">Doğum Tarihi</label><input type="date" id="st_birth" name="birth_date" value="<?php echo platform_e($v['birth_date']); ?>"></div>
              <div class="form-field"><label for="st_type">Koçluk Tipi</label>
                <select id="st_type" name="coaching_type">
                  <?php foreach (['Öğrenci Koçluğu', 'Kariyer Koçluğu', 'Koçluk Süreci'] as $type): ?>
                    <option value="<?php echo platform_e($type); ?>" <?php echo $v['coaching_type'] === $type ? 'selected' : ''; ?>><?php echo platform_e($type); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <h4 class="mt-24 mb-16">Eğitim ve Durum</h4>
            <div class="admin-form-row">
              <div class="form-field"><label for="st_school">Okul</label><input type="text" id="st_school" name="school" value="<?php echo platform_e($v['school']); ?>"></div>
              <div class="form-field"><label for="st_grade">Sınıf / Seviye</label><input type="text" id="st_grade" name="grade_level" value="<?php echo platform_e($v['grade_level']); ?>"></div>
            </div>
            <div class="admin-form-row">
              <div class="form-field"><label for="st_status">Öğrenci Durumu</label>
                <select id="st_status" name="status">
                  <?php foreach (platform_student_statuses() as $key => $label): ?>
                    <option value="<?php echo platform_e($key); ?>" <?php echo $v['status'] === $key ? 'selected' : ''; ?>><?php echo platform_e($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-field"><label for="st_risk">Risk Seviyesi</label>
                <select id="st_risk" name="risk_level">
                  <?php foreach (platform_risk_levels() as $key => $label): ?>
                    <option value="<?php echo platform_e($key); ?>" <?php echo $v['risk_level'] === $key ? 'selected' : ''; ?>><?php echo platform_e($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <h4 class="mt-24 mb-16">Veli / Onay</h4>
            <div class="admin-form-row">
              <div class="form-field"><label for="st_gname">Veli Adı</label><input type="text" id="st_gname" name="guardian_name" value="<?php echo platform_e($v['guardian_name']); ?>"></div>
              <div class="form-field"><label for="st_gphone">Veli Telefonu</label><input type="text" id="st_gphone" name="guardian_phone" value="<?php echo platform_e($v['guardian_phone']); ?>"></div>
            </div>
            <div class="form-field"><label for="st_gemail">Veli E-posta</label><input type="email" id="st_gemail" name="guardian_email" value="<?php echo platform_e($v['guardian_email']); ?>"></div>
            <label class="admin-check-row">
              <input type="checkbox" name="consent" value="1" <?php echo $v['consent_at'] ? 'checked' : ''; ?>>
              <span>KVKK aydınlatma metnini okuduğuna dair onay alındı <?php echo $v['consent_at'] ? '(' . platform_e(substr((string) $v['consent_at'], 0, 16)) . ')' : ''; ?></span>
            </label>

            <div class="form-field mt-16"><label for="st_notes">Koç Notları (genel)</label><textarea id="st_notes" name="notes" rows="4" style="min-height:100px;"><?php echo platform_e($v['notes']); ?></textarea></div>

            <div class="admin-form-actions">
              <a href="admin.php?action=students" class="btn btn-secondary">Vazgeç</a>
              <button type="submit" class="btn btn-primary btn-lg">Öğrenciyi Kaydet</button>
            </div>
          </form>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'student-view') {
    $sid = (int) ($_GET['id'] ?? 0);
    $student = $sid > 0 ? platform_get_student($sid) : null;
    if (!$student) {
        http_response_code(404);
        echo 'Öğrenci bulunamadı.';
        exit;
    }
    $sessions = platform_get_sessions($sid);
    $goals = platform_get_goals($sid);
    $tasks = platform_get_actions($sid);
    $appts = platform_get_appointments_for_student($sid);
    $unlinked = platform_get_unlinked_appointments();
    $consents = platform_get_consents($sid);
    $templates = platform_get_templates();
    $studentNotifications = platform_get_notifications($sid, 5);
    $portalMetaStudent = platform_portal_token_meta($sid, 'student');
    $portalMetaGuardian = platform_portal_token_meta($sid, 'guardian');
    $portalLink = $_SESSION['admin_portal_link'] ?? null;
    unset($_SESSION['admin_portal_link']);

    $editSession = (int) ($_GET['edit_session'] ?? 0);
    $editGoal = (int) ($_GET['edit_goal'] ?? 0);
    $editAction = (int) ($_GET['edit_action'] ?? 0);
    $sessionRow = $editSession > 0 ? platform_get_session($editSession) : null;
    $goalRow = null;
    foreach ($goals as $g) { if ((int) $g['id'] === $editGoal) { $goalRow = $g; break; } }
    $actionRow = null;
    foreach ($tasks as $t) { if ((int) $t['id'] === $editAction) { $actionRow = $t; break; } }
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title><?php echo platform_e($student['first_name'] . ' ' . $student['last_name']); ?> | Öğrenci Dosyası</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div>
              <span class="eyebrow"><?php echo platform_e($student['student_code']); ?></span>
              <h1><?php echo platform_e($student['first_name'] . ' ' . $student['last_name']); ?></h1>
              <div class="admin-student-meta">
                <span class="admin-pill admin-pill-<?php echo platform_e($student['status']); ?>"><?php echo platform_e(platform_student_statuses()[$student['status']] ?? $student['status']); ?></span>
                <span class="admin-pill admin-pill-risk-<?php echo platform_e($student['risk_level']); ?>">Risk: <?php echo platform_e(platform_risk_levels()[$student['risk_level']] ?? $student['risk_level']); ?></span>
                <span><?php echo platform_e($student['coaching_type']); ?></span>
                <?php if ($student['school']): ?><span><?php echo platform_e($student['school']); ?></span><?php endif; ?>
              </div>
            </div>
            <div class="btn-group">
              <a href="admin.php?action=student-edit&id=<?php echo $sid; ?>" class="btn btn-secondary"><?php echo icon("pencil", 14); ?> Düzenle</a>
              <a href="admin.php?action=appointments-edit" class="btn btn-primary">＋ Randevu Ekle</a>
              <a href="<?php echo platform_url('report-pdf', ['id' => $sid, 'scope' => 'coach'] + platform_report_period_params()); ?>" class="btn btn-secondary"><?php echo icon("file-text", 16); ?> Koç Raporu</a>
              <a href="<?php echo platform_url('report-pdf', ['id' => $sid, 'scope' => 'shared'] + platform_report_period_params()); ?>" class="btn btn-secondary"><?php echo icon("file-text", 16); ?> Paylaşılan Rapor</a>
            </div>
          </div>

          <?php platform_student_tabs($sid, 'student-view'); ?>

          <div class="admin-mini-stats mt-24">
            <div><strong><?php echo count($sessions); ?></strong><span>Seans</span></div>
            <div><strong><?php echo count(array_filter($goals, function ($g) { return $g['status'] === 'active'; })); ?></strong><span>Aktif Hedef</span></div>
            <div><strong><?php echo count(array_filter($tasks, function ($t) { return $t['status'] !== 'done'; })); ?></strong><span>Açık Ödev</span></div>
            <div><strong><?php echo $student['consent_at'] ? 'Var' : 'Yok'; ?></strong><span>KVKK Onayı</span></div>
          </div>

          <section class="admin-section mt-24">
            <div class="admin-section-head"><h3><?php echo icon("lock", 16); ?> Portal Erişimi</h3><span class="admin-muted">Bağlantıyı yalnızca ilgili kişiyle paylaşın</span></div>
            <?php if ($portalLink): ?>
              <div class="admin-portal-link">
                <strong><?php echo platform_e(portal_role_label((string) $portalLink['role'])); ?> bağlantısı — bu kez gösterilir:</strong>
                <input type="text" readonly value="<?php echo platform_e((string) $portalLink['url']); ?>" onclick="this.select();" aria-label="Portal bağlantısı">
                <span class="admin-muted">Geçerlilik: <?php echo platform_e(substr((string) $portalLink['expires_at'], 0, 16)); ?></span>
              </div>
            <?php endif; ?>
            <div class="admin-portal-grid">
              <?php foreach (['student' => $portalMetaStudent, 'guardian' => $portalMetaGuardian] as $role => $meta): ?>
                <div class="admin-portal-card">
                  <h4><?php echo platform_e(portal_role_label($role)); ?> Portalı</h4>
                  <p class="admin-muted">
                    <?php if ($meta && (int) $meta['active'] === 1): ?>
                      Bağlantı aktif · son kullanım: <?php echo platform_e($meta['last_used_at'] ? substr((string) $meta['last_used_at'], 0, 16) : 'Henüz yok'); ?><br>
                      Bitiş: <?php echo platform_e(substr((string) $meta['expires_at'], 0, 16)); ?>
                    <?php else: ?>
                      Aktif bağlantı yok.
                    <?php endif; ?>
                  </p>
                  <div class="btn-group">
                    <form method="post" action="admin.php?action=portal-token-issue">
                      <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                      <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                      <input type="hidden" name="role" value="<?php echo $role; ?>">
                      <button type="submit" class="btn btn-primary btn-mini">Bağlantı Oluştur</button>
                    </form>
                    <?php if ($meta && (int) $meta['active'] === 1): ?>
                      <form method="post" action="admin.php?action=portal-token-revoke" onsubmit="return confirm('Bağlantı iptal edilsin mi?');">
                        <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                        <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                        <input type="hidden" name="role" value="<?php echo $role; ?>">
                        <button type="submit" class="btn btn-wa btn-mini">İptal Et</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($studentNotifications): ?>
              <div class="admin-section mt-24">
                <div class="admin-section-head"><h4>Son Bildirimler</h4><a href="admin.php?action=notifications" class="admin-link">Tümünü gör</a></div>
                <?php foreach ($studentNotifications as $n): ?>
                  <div class="admin-consent-row">
                    <span><?php echo platform_e(substr((string) $n['created_at'], 0, 16)); ?> · <?php echo platform_e($n['kind']); ?></span>
                    <span class="admin-pill admin-pill-<?php echo $n['status'] === 'sent' ? 'active' : 'pending'; ?>"><?php echo platform_e($n['status']); ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <?php if ($unlinked): ?>
          <details class="admin-details mt-24">
            <summary><?php echo icon("external", 14); ?> Bağlı olmayan bir randevuyu bu öğrenciye bağla</summary>
            <form method="post" action="admin.php?action=student-link-appointment" class="admin-inline-form">
              <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
              <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
              <select name="appointment_id" required>
                <option value="">Randevu seçin...</option>
                <?php foreach ($unlinked as $ap): ?>
                  <option value="<?php echo (int) $ap['id']; ?>"><?php echo platform_e($ap['appointment_date'] . ' ' . substr((string) $ap['appointment_time'], 0, 5) . ' — ' . $ap['name']); ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary btn-mini">Bağla</button>
            </form>
          </details>
          <?php endif; ?>

          <!-- SEANSLAR -->
          <section class="admin-section mt-32">
            <div class="admin-section-head">
              <h3><?php echo icon("calendar-month", 16); ?>️ Seanslar ve Notlar</h3>
              <button type="button" class="btn btn-secondary btn-mini" onclick="var d=document.getElementById('sessionFormBox'); d.open=!d.open;">＋ Seans Ekle</button>
            </div>

            <details class="admin-details" id="sessionFormBox" <?php echo $sessionRow ? 'open' : ''; ?>>
              <summary><?php echo $sessionRow ? 'Seansı düzenle' : 'Yeni seans notu oluştur'; ?></summary>
              <form method="post" action="admin.php?action=session-save" class="admin-form platform-form">
                <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                <?php if ($sessionRow): ?><input type="hidden" name="id" value="<?php echo (int) $sessionRow['id']; ?>"><?php endif; ?>
                <div class="admin-form-row">
                  <div class="form-field"><label for="se_date">Tarih *</label><input type="date" id="se_date" name="session_date" required value="<?php echo platform_e($sessionRow['session_date'] ?? date('Y-m-d')); ?>"></div>
                  <div class="form-field"><label for="se_time">Saat *</label><input type="time" id="se_time" name="session_time" step="3600" required value="<?php echo platform_e(substr((string) ($sessionRow['session_time'] ?? '09:00:00'), 0, 5)); ?>"></div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="se_type">Seans Tipi</label><input type="text" id="se_type" name="session_type" value="<?php echo platform_e($sessionRow['session_type'] ?? 'Düzenli Seans'); ?>"></div>
                  <div class="form-field"><label for="se_template">Not Şablonu</label>
                    <select id="se_template" name="template_id">
                      <option value="0">Şablonsuz</option>
                      <?php foreach ($templates as $tpl): ?>
                        <option value="<?php echo (int) $tpl['id']; ?>" <?php echo $sessionRow && (int) $sessionRow['template_id'] === (int) $tpl['id'] ? 'selected' : ''; ?>><?php echo platform_e($tpl['name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="se_status">Durum</label>
                    <select id="se_status" name="status">
                      <?php foreach (['planned' => 'Planlandı', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal', 'no_show' => 'Gelmedi'] as $key => $label): ?>
                        <option value="<?php echo platform_e($key); ?>" <?php echo ($sessionRow['status'] ?? 'planned') === $key ? 'selected' : ''; ?>><?php echo platform_e($label); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-field"><label for="se_appointment">Bağlı Randevu</label>
                    <select id="se_appointment" name="appointment_id">
                      <option value="0">Yok</option>
                      <?php foreach ($appts as $ap): ?>
                        <option value="<?php echo (int) $ap['id']; ?>" <?php echo $sessionRow && (int) $sessionRow['appointment_id'] === (int) $ap['id'] ? 'selected' : ''; ?>><?php echo platform_e($ap['appointment_date'] . ' ' . substr((string) $ap['appointment_time'], 0, 5)); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="se_mood1">Seans Öncesi Ruh Hali (1-5)</label>
                    <select id="se_mood1" name="mood_before"><option value="">—</option><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?php echo $i; ?>" <?php echo ($sessionRow['mood_before'] ?? '') == $i ? 'selected' : ''; ?>><?php echo $i; ?></option><?php endfor; ?></select>
                  </div>
                  <div class="form-field"><label for="se_mood2">Seans Sonrası Ruh Hali (1-5)</label>
                    <select id="se_mood2" name="mood_after"><option value="">—</option><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?php echo $i; ?>" <?php echo ($sessionRow['mood_after'] ?? '') == $i ? 'selected' : ''; ?>><?php echo $i; ?></option><?php endfor; ?></select>
                  </div>
                </div>
                <div class="form-field"><label for="se_shared">Öğrenciye Gösterilecek Özet</label><textarea id="se_shared" name="shared_note" rows="5" style="min-height:110px;"><?php echo platform_e($sessionRow['shared_note'] ?? ''); ?></textarea></div>
                <div class="form-field"><label for="se_private">Özel Koç Notu <span class="admin-hint">(öğrenci göremez)</span></label><textarea id="se_private" name="private_note" rows="5" style="min-height:110px;"><?php echo platform_e($sessionRow['private_note'] ?? ''); ?></textarea></div>
                <div class="admin-form-actions">
                  <?php if ($sessionRow): ?><a href="admin.php?action=student-view&id=<?php echo $sid; ?>" class="btn btn-secondary">Vazgeç</a><?php endif; ?>
                  <button type="submit" class="btn btn-primary">Seans Kaydet</button>
                </div>
              </form>
            </details>

            <div class="card admin-card" style="padding:0;overflow:hidden;">
              <div class="admin-table-wrap">
                <table class="admin-table admin-subtable">
                  <thead><tr><th>Tarih</th><th>Tip</th><th>Durum</th><th>Paylaşılan Özet</th><th>Özel Not</th><th style="text-align:right;">İşlemler</th></tr></thead>
                  <tbody>
                    <?php if (!$sessions): ?><tr><td colspan="6" class="admin-table-empty">Henüz seans kaydı yok.</td></tr><?php endif; ?>
                    <?php foreach ($sessions as $se): ?>
                    <tr>
                      <td><?php echo platform_e($se['session_date'] . ' ' . substr((string) $se['session_time'], 0, 5)); ?></td>
                      <td><?php echo platform_e($se['session_type']); ?></td>
                      <td><span class="admin-pill admin-pill-<?php echo $se['status'] === 'completed' ? 'active' : 'pending'; ?>"><?php echo platform_e(['planned' => 'Planlandı', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal', 'no_show' => 'Gelmedi'][$se['status']] ?? $se['status']); ?></span></td>
                      <td class="admin-note-preview"><?php echo $se['shared_note'] ? nl2br(platform_e(mb_substr($se['shared_note'], 0, 140))) : '—'; ?></td>
                      <td class="admin-note-preview private"><?php echo $se['private_note'] ? nl2br(platform_e(mb_substr($se['private_note'], 0, 140))) : '—'; ?></td>
                      <td class="admin-actions">
                        <a href="admin.php?action=student-view&id=<?php echo $sid; ?>&edit_session=<?php echo (int) $se['id']; ?>#sessionFormBox" class="btn btn-secondary btn-mini"><?php echo icon("pencil", 14); ?></a>
                        <a href="<?php echo platform_url('session-delete', ['id' => (int) $se['id'], 'student_id' => $sid]); ?>" class="btn btn-wa btn-mini" onclick="return confirm('Seans silinsin mi?');"><?php echo icon("trash", 14); ?></a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <!-- HEDEFLER -->
          <section class="admin-section mt-32">
            <div class="admin-section-head"><h3><?php echo icon("target", 18); ?> Hedefler</h3></div>
            <details class="admin-details" <?php echo $goalRow ? 'open' : ''; ?>>
              <summary><?php echo $goalRow ? 'Hedefi düzenle' : '＋ Yeni hedef ekle'; ?></summary>
              <form method="post" action="admin.php?action=goal-save" class="admin-form platform-form">
                <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                <?php if ($goalRow): ?><input type="hidden" name="id" value="<?php echo (int) $goalRow['id']; ?>"><?php endif; ?>
                <div class="form-field"><label for="go_title">Hedef Başlığı *</label><input type="text" id="go_title" name="title" required value="<?php echo platform_e($goalRow['title'] ?? ''); ?>"></div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="go_cat">Kategori</label><input type="text" id="go_cat" name="category" value="<?php echo platform_e($goalRow['category'] ?? 'Akademik'); ?>"></div>
                  <div class="form-field"><label for="go_date">Hedef Tarihi</label><input type="date" id="go_date" name="target_date" value="<?php echo platform_e($goalRow['target_date'] ?? ''); ?>"></div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="go_status">Durum</label>
                    <select id="go_status" name="status">
                      <?php foreach (['active' => 'Aktif', 'completed' => 'Tamamlandı', 'paused' => 'Beklemede'] as $key => $label): ?>
                        <option value="<?php echo platform_e($key); ?>" <?php echo ($goalRow['status'] ?? 'active') === $key ? 'selected' : ''; ?>><?php echo platform_e($label); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-field"><label for="go_progress">İlerleme (%)</label><input type="number" id="go_progress" name="progress" min="0" max="100" value="<?php echo (int) ($goalRow['progress'] ?? 0); ?>"></div>
                </div>
                <div class="form-field"><label for="go_desc">Açıklama</label><textarea id="go_desc" name="description" rows="3" style="min-height:80px;"><?php echo platform_e($goalRow['description'] ?? ''); ?></textarea></div>
                <div class="admin-form-actions"><button type="submit" class="btn btn-primary">Hedefi Kaydet</button></div>
              </form>
            </details>

            <div class="card admin-card" style="padding:0;overflow:hidden;">
              <div class="admin-table-wrap">
                <table class="admin-table admin-subtable">
                  <thead><tr><th>Hedef</th><th>Kategori</th><th>Tarih</th><th>İlerleme</th><th>Durum</th><th style="text-align:right;">İşlemler</th></tr></thead>
                  <tbody>
                    <?php if (!$goals): ?><tr><td colspan="6" class="admin-table-empty">Henüz hedef eklenmedi.</td></tr><?php endif; ?>
                    <?php foreach ($goals as $g): ?>
                    <tr>
                      <td class="admin-post-title"><?php echo platform_e($g['title']); ?></td>
                      <td><?php echo platform_e($g['category']); ?></td>
                      <td><?php echo platform_e($g['target_date'] ?: '—'); ?></td>
                      <td><div class="admin-progress"><span style="width:<?php echo (int) $g['progress']; ?>%"></span></div><small><?php echo (int) $g['progress']; ?>%</small></td>
                      <td><span class="admin-pill admin-pill-<?php echo $g['status'] === 'active' ? 'active' : 'pending'; ?>"><?php echo platform_e(['active' => 'Aktif', 'completed' => 'Tamamlandı', 'paused' => 'Beklemede'][$g['status']] ?? $g['status']); ?></span></td>
                      <td class="admin-actions">
                        <a href="admin.php?action=student-view&id=<?php echo $sid; ?>&edit_goal=<?php echo (int) $g['id']; ?>" class="btn btn-secondary btn-mini"><?php echo icon("pencil", 14); ?></a>
                        <a href="<?php echo platform_url('goal-delete', ['id' => (int) $g['id'], 'student_id' => $sid]); ?>" class="btn btn-wa btn-mini" onclick="return confirm('Hedef silinsin mi?');"><?php echo icon("trash", 14); ?></a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <!-- ÖDEVLER -->
          <section class="admin-section mt-32">
            <div class="admin-section-head"><h3><?php echo icon("check-circle", 16); ?> Ödevler ve Aksiyonlar</h3></div>
            <details class="admin-details" <?php echo $actionRow ? 'open' : ''; ?>>
              <summary><?php echo $actionRow ? 'Ödevi düzenle' : '＋ Yeni ödev ekle'; ?></summary>
              <form method="post" action="admin.php?action=action-save" class="admin-form platform-form">
                <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                <?php if ($actionRow): ?><input type="hidden" name="id" value="<?php echo (int) $actionRow['id']; ?>"><?php endif; ?>
                <div class="form-field"><label for="ac_title">Ödev Başlığı *</label><input type="text" id="ac_title" name="title" required value="<?php echo platform_e($actionRow['title'] ?? ''); ?>"></div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="ac_goal">Bağlı Hedef</label>
                    <select id="ac_goal" name="goal_id"><option value="0">Yok</option>
                      <?php foreach ($goals as $g): ?><option value="<?php echo (int) $g['id']; ?>" <?php echo $actionRow && (int) $actionRow['goal_id'] === (int) $g['id'] ? 'selected' : ''; ?>><?php echo platform_e($g['title']); ?></option><?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-field"><label for="ac_due">Teslim Tarihi</label><input type="date" id="ac_due" name="due_date" value="<?php echo platform_e($actionRow['due_date'] ?? ''); ?>"></div>
                </div>
                <div class="form-field"><label for="ac_status">Durum</label>
                  <select id="ac_status" name="status">
                    <?php foreach (['open' => 'Açık', 'in_progress' => 'Devam Ediyor', 'done' => 'Tamamlandı', 'overdue' => 'Gecikti'] as $key => $label): ?>
                      <option value="<?php echo platform_e($key); ?>" <?php echo ($actionRow['status'] ?? 'open') === $key ? 'selected' : ''; ?>><?php echo platform_e($label); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-field"><label for="ac_desc">Açıklama</label><textarea id="ac_desc" name="description" rows="3" style="min-height:80px;"><?php echo platform_e($actionRow['description'] ?? ''); ?></textarea></div>
                <div class="admin-form-actions"><button type="submit" class="btn btn-primary">Ödevi Kaydet</button></div>
              </form>
            </details>

            <div class="card admin-card" style="padding:0;overflow:hidden;">
              <div class="admin-table-wrap">
                <table class="admin-table admin-subtable">
                  <thead><tr><th style="width:36px;"></th><th>Ödev</th><th>Hedef</th><th>Teslim</th><th>Durum</th><th style="text-align:right;">İşlemler</th></tr></thead>
                  <tbody>
                    <?php if (!$tasks): ?><tr><td colspan="6" class="admin-table-empty">Henüz ödev eklenmedi.</td></tr><?php endif; ?>
                    <?php foreach ($tasks as $t): ?>
                    <tr>
                      <td><a href="<?php echo platform_url('action-toggle', ['id' => (int) $t['id'], 'student_id' => $sid]); ?>" title="Tamamlandı / açık değiştir"><?php echo $t['status'] === 'done' ? '<?php echo icon("check-circle", 18); ?>' : '<?php echo icon("check", 18); ?>'; ?></a></td>
                      <td><div class="admin-post-title <?php echo $t['status'] === 'done' ? 'done' : ''; ?>"><?php echo platform_e($t['title']); ?></div></td>
                      <td><?php echo platform_e($t['goal_title'] ?: '—'); ?></td>
                      <td><?php echo platform_e($t['due_date'] ?: '—'); ?></td>
                      <td><span class="admin-pill admin-pill-<?php echo $t['status'] === 'done' ? 'active' : ($t['status'] === 'overdue' ? 'at_risk' : 'pending'); ?>"><?php echo platform_e(['open' => 'Açık', 'in_progress' => 'Devam', 'done' => 'Tamamlandı', 'overdue' => 'Gecikti'][$t['status']] ?? $t['status']); ?></span></td>
                      <td class="admin-actions">
                        <a href="admin.php?action=student-view&id=<?php echo $sid; ?>&edit_action=<?php echo (int) $t['id']; ?>" class="btn btn-secondary btn-mini"><?php echo icon("pencil", 14); ?></a>
                        <a href="<?php echo platform_url('action-delete', ['id' => (int) $t['id'], 'student_id' => $sid]); ?>" class="btn btn-wa btn-mini" onclick="return confirm('Ödev silinsin mi?');"><?php echo icon("trash", 14); ?></a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <!-- RANDEVULAR VE ONAYLAR -->
          <div class="grid-2 mt-32" style="align-items:start;">
            <section class="admin-section">
              <div class="admin-section-head"><h3><?php echo icon("calendar", 16); ?> Bağlı Randevular</h3></div>
              <div class="card admin-card" style="padding:0;overflow:hidden;">
                <div class="admin-table-wrap">
                  <table class="admin-table admin-subtable">
                    <thead><tr><th>Ad Soyad</th><th>Tarih / Saat</th><th>Tip</th><th>Durum</th></tr></thead>
                    <tbody>
                      <?php if (!$appts): ?><tr><td colspan="4" class="admin-table-empty">Bağlı randevu yok.</td></tr><?php endif; ?>
                      <?php foreach ($appts as $ap): ?>
                      <tr>
                        <td class="admin-post-title"><?php echo platform_e($ap['name']); ?></td>
                        <td><?php echo platform_e($ap['appointment_date'] . ' ' . substr((string) $ap['appointment_time'], 0, 5)); ?></td>
                        <td><?php echo platform_e($ap['coaching_type']); ?></td>
                        <td><?php echo platform_e($ap['status']); ?></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>
            <section class="admin-section">
              <div class="admin-section-head"><h3><?php echo icon("lock", 16); ?> KVKK Onay Kayıtları</h3></div>
              <div class="card" style="padding:20px;">
                <?php if (!$consents): ?>
                  <p class="admin-muted">Onay kaydı bulunmuyor.</p>
                <?php else: ?>
                  <?php foreach ($consents as $c): ?>
                    <div class="admin-consent-row">
                      <span><?php echo platform_e($c['consent_type']); ?> · v<?php echo platform_e($c['text_version']); ?></span>
                      <span class="admin-post-slug"><?php echo platform_e($c['granted_at']); ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($student['notes']): ?>
                  <div class="mt-16"><strong>Genel koç notu:</strong><div class="admin-note-preview"><?php echo nl2br(platform_e($student['notes'])); ?></div></div>
                <?php endif; ?>
              </div>
            </section>
          </div>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'student-academics') {
    $sid = (int) ($_GET['id'] ?? 0);
    $student = $sid > 0 ? platform_get_student($sid) : null;
    if (!$student) { http_response_code(404); echo 'Öğrenci bulunamadı.'; exit; }

    $subjects = platform_get_subjects($sid);
    $lessons = platform_get_lessons($sid);
    $exams = platform_get_exams($sid);
    $editSubjectId = (int) ($_GET['edit_subject'] ?? 0);
    $editLessonId = (int) ($_GET['edit_lesson'] ?? 0);
    $editExamId = (int) ($_GET['edit_exam'] ?? 0);
    $findRow = function (array $rows, int $id): ?array {
        foreach ($rows as $r) { if ((int) $r['id'] === $id) { return $r; } }
        return null;
    };
    $subjectRow = $findRow($subjects, $editSubjectId);
    $lessonRow = $findRow($lessons, $editLessonId);
    $examRow = $findRow($exams, $editExamId);
    $subjectOptions = '<option value="0">Ders seçilmedi</option>';
    foreach ($subjects as $s) {
        $subjectOptions .= '<option value="' . (int) $s['id'] . '"';
        if (($lessonRow['subject_id'] ?? 0) == $s['id'] || ($examRow['subject_id'] ?? 0) == $s['id']) { $subjectOptions .= ' selected'; }
        $subjectOptions .= '>' . platform_e($s['name']) . '</option>';
    }
    $subjectStatuses = ['active' => 'Aktif', 'completed' => 'Tamamlandı', 'paused' => 'Beklemede'];
    $lessonStatuses = ['planned' => 'Planlandı', 'done' => 'Tamamlandı', 'cancelled' => 'İptal'];
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Akademik Takip | <?php echo platform_e($student['first_name'] . ' ' . $student['last_name']); ?></title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div><span class="eyebrow"><?php echo platform_e($student['student_code']); ?></span><h1>Akademik Takip</h1></div>
            <a href="admin.php?action=students" class="btn btn-secondary">← Öğrenciler</a>
          </div>
          <?php platform_student_tabs($sid, 'student-academics'); ?>

          <!-- DERSLER -->
          <section class="admin-section mt-24">
            <div class="admin-section-head"><h3><?php echo icon("book", 18); ?> Dersler</h3><span class="admin-muted"><?php echo count($subjects); ?> ders</span></div>
            <details class="admin-details" <?php echo $subjectRow ? 'open' : ''; ?>>
              <summary><?php echo $subjectRow ? 'Dersi düzenle' : '＋ Yeni ders ekle'; ?></summary>
              <form method="post" action="admin.php?action=subject-save" class="admin-form platform-form">
                <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                <?php if ($subjectRow): ?><input type="hidden" name="id" value="<?php echo (int) $subjectRow['id']; ?>"><?php endif; ?>
                <div class="admin-form-row">
                  <div class="form-field"><label for="su_name">Ders Adı *</label><input type="text" id="su_name" name="name" required value="<?php echo platform_e($subjectRow['name'] ?? ''); ?>"></div>
                  <div class="form-field"><label for="su_teacher">Öğretmen</label><input type="text" id="su_teacher" name="teacher" value="<?php echo platform_e($subjectRow['teacher'] ?? ''); ?>"></div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="su_term">Dönem</label><input type="text" id="su_term" name="term" value="<?php echo platform_e($subjectRow['term'] ?? ''); ?>" placeholder="2026-2027 Güz"></div>
                  <div class="form-field"><label for="su_status">Durum</label><select id="su_status" name="status">
                    <?php foreach ($subjectStatuses as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo ($subjectRow['status'] ?? 'active') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?>
                  </select></div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="su_target">Hedef Not</label><input type="text" id="su_target" name="target_grade" value="<?php echo platform_e($subjectRow['target_grade'] ?? ''); ?>"></div>
                  <div class="form-field"><label for="su_current">Güncel Not</label><input type="text" id="su_current" name="current_grade" value="<?php echo platform_e($subjectRow['current_grade'] ?? ''); ?>"></div>
                </div>
                <div class="form-field"><label for="su_notes">Notlar</label><textarea id="su_notes" name="notes" rows="3" style="min-height:80px;"><?php echo platform_e($subjectRow['notes'] ?? ''); ?></textarea></div>
                <div class="admin-form-actions"><button type="submit" class="btn btn-primary">Dersi Kaydet</button></div>
              </form>
            </details>
            <div class="card admin-card" style="padding:0;overflow:hidden;">
              <div class="admin-table-wrap">
                <table class="admin-table admin-subtable">
                  <thead><tr><th>Ders</th><th>Öğretmen</th><th>Dönem</th><th>Hedef</th><th>Güncel</th><th>Durum</th><th style="text-align:right;">İşlemler</th></tr></thead>
                  <tbody>
                    <?php if (!$subjects): ?><tr><td colspan="7" class="admin-table-empty">Henüz ders eklenmedi.</td></tr><?php endif; ?>
                    <?php foreach ($subjects as $s): ?>
                    <tr>
                      <td class="admin-post-title"><?php echo platform_e($s['name']); ?></td>
                      <td><?php echo platform_e($s['teacher'] ?: '—'); ?></td>
                      <td><?php echo platform_e($s['term'] ?: '—'); ?></td>
                      <td><?php echo platform_e($s['target_grade'] ?: '—'); ?></td>
                      <td><?php echo platform_e($s['current_grade'] ?: '—'); ?></td>
                      <td><span class="admin-pill admin-pill-<?php echo $s['status'] === 'active' ? 'active' : 'pending'; ?>"><?php echo platform_e(platform_label($subjectStatuses, $s['status'])); ?></span></td>
                      <td class="admin-actions">
                        <a href="admin.php?action=student-academics&id=<?php echo $sid; ?>&edit_subject=<?php echo (int) $s['id']; ?>" class="btn btn-secondary btn-mini"><?php echo icon("pencil", 14); ?></a>
                        <a href="<?php echo platform_url('subject-delete', ['id' => (int) $s['id'], 'student_id' => $sid]); ?>" class="btn btn-wa btn-mini" onclick="return confirm('Ders silinsin mi?');"><?php echo icon("trash", 14); ?></a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <!-- ÇALIŞMA KAYITLARI -->
          <section class="admin-section mt-32">
            <div class="admin-section-head"><h3><?php echo icon("pencil", 14); ?> Çalışma / Ders Seansları</h3><span class="admin-muted"><?php echo count($lessons); ?> kayıt</span></div>
            <details class="admin-details" <?php echo $lessonRow ? 'open' : ''; ?>>
              <summary><?php echo $lessonRow ? 'Kaydı düzenle' : '＋ Çalışma kaydı ekle'; ?></summary>
              <form method="post" action="admin.php?action=lesson-save" class="admin-form platform-form">
                <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                <?php if ($lessonRow): ?><input type="hidden" name="id" value="<?php echo (int) $lessonRow['id']; ?>"><?php endif; ?>
                <div class="admin-form-row">
                  <div class="form-field"><label for="le_date">Tarih *</label><input type="date" id="le_date" name="lesson_date" required value="<?php echo platform_e($lessonRow['lesson_date'] ?? date('Y-m-d')); ?>"></div>
                  <div class="form-field"><label for="le_subject">Ders</label><select id="le_subject" name="subject_id"><?php echo $subjectOptions; ?></select></div>
                </div>
                <div class="form-field"><label for="le_topic">Konu *</label><input type="text" id="le_topic" name="topic" required value="<?php echo platform_e($lessonRow['topic'] ?? ''); ?>"></div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="le_duration">Süre (dk)</label><input type="number" id="le_duration" name="duration_min" min="15" step="15" value="<?php echo (int) ($lessonRow['duration_min'] ?? 60); ?>"></div>
                  <div class="form-field"><label for="le_status">Durum</label><select id="le_status" name="status">
                    <?php foreach ($lessonStatuses as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo ($lessonRow['status'] ?? 'planned') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?>
                  </select></div>
                </div>
                <div class="form-field"><label for="le_coach">Koç Notu</label><textarea id="le_coach" name="coach_note" rows="3" style="min-height:80px;"><?php echo platform_e($lessonRow['coach_note'] ?? ''); ?></textarea></div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="le_feedback">Öğrenci Geri Bildirimi</label><textarea id="le_feedback" name="student_feedback" rows="3" style="min-height:80px;"><?php echo platform_e($lessonRow['student_feedback'] ?? ''); ?></textarea></div>
                  <div class="form-field"><label for="le_homework">Çalışma / Ödev</label><textarea id="le_homework" name="homework" rows="3" style="min-height:80px;"><?php echo platform_e($lessonRow['homework'] ?? ''); ?></textarea></div>
                </div>
                <div class="admin-form-actions"><button type="submit" class="btn btn-primary">Çalışmayı Kaydet</button></div>
              </form>
            </details>
            <div class="card admin-card" style="padding:0;overflow:hidden;">
              <div class="admin-table-wrap">
                <table class="admin-table admin-subtable">
                  <thead><tr><th>Tarih</th><th>Ders</th><th>Konu</th><th>Süre</th><th>Durum</th><th style="text-align:right;">İşlemler</th></tr></thead>
                  <tbody>
                    <?php if (!$lessons): ?><tr><td colspan="6" class="admin-table-empty">Henüz çalışma kaydı yok.</td></tr><?php endif; ?>
                    <?php foreach ($lessons as $l): ?>
                    <tr>
                      <td><?php echo platform_e($l['lesson_date']); ?></td>
                      <td><?php echo platform_e($l['subject_name'] ?: '—'); ?></td>
                      <td class="admin-post-title"><?php echo platform_e($l['topic']); ?></td>
                      <td><?php echo (int) $l['duration_min']; ?> dk</td>
                      <td><span class="admin-pill admin-pill-<?php echo $l['status'] === 'done' ? 'active' : 'pending'; ?>"><?php echo platform_e(platform_label($lessonStatuses, $l['status'])); ?></span></td>
                      <td class="admin-actions">
                        <a href="admin.php?action=student-academics&id=<?php echo $sid; ?>&edit_lesson=<?php echo (int) $l['id']; ?>" class="btn btn-secondary btn-mini"><?php echo icon("pencil", 14); ?></a>
                        <a href="<?php echo platform_url('lesson-delete', ['id' => (int) $l['id'], 'student_id' => $sid]); ?>" class="btn btn-wa btn-mini" onclick="return confirm('Kayıt silinsin mi?');"><?php echo icon("trash", 14); ?></a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <!-- SINAVLAR -->
          <section class="admin-section mt-32">
            <div class="admin-section-head"><h3><?php echo icon("pencil", 14); ?> Sınavlar</h3><span class="admin-muted"><?php echo count($exams); ?> kayıt</span></div>
            <details class="admin-details" <?php echo $examRow ? 'open' : ''; ?>>
              <summary><?php echo $examRow ? 'Sınavı düzenle' : '＋ Sınav sonucu ekle'; ?></summary>
              <form method="post" action="admin.php?action=exam-save" class="admin-form platform-form">
                <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                <?php if ($examRow): ?><input type="hidden" name="id" value="<?php echo (int) $examRow['id']; ?>"><?php endif; ?>
                <div class="admin-form-row">
                  <div class="form-field"><label for="ex_name">Sınav Adı *</label><input type="text" id="ex_name" name="exam_name" required value="<?php echo platform_e($examRow['exam_name'] ?? ''); ?>" placeholder="1. Deneme / Matematik"></div>
                  <div class="form-field"><label for="ex_date">Tarih *</label><input type="date" id="ex_date" name="exam_date" required value="<?php echo platform_e($examRow['exam_date'] ?? date('Y-m-d')); ?>"></div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="ex_subject">Ders</label><select id="ex_subject" name="subject_id"><?php echo $subjectOptions; ?></select></div>
                  <div class="form-field"><label for="ex_score">Puan / Net</label><input type="number" step="0.01" id="ex_score" name="score" value="<?php echo platform_e($examRow['score'] ?? ''); ?>"></div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="ex_target">Hedef</label><input type="number" step="0.01" id="ex_target" name="target_score" value="<?php echo platform_e($examRow['target_score'] ?? ''); ?>"></div>
                  <div class="form-field"><label for="ex_max">Maksimum</label><input type="number" step="0.01" id="ex_max" name="max_score" value="<?php echo platform_e($examRow['max_score'] ?? '100'); ?>"></div>
                </div>
                <div class="form-field"><label for="ex_note">Not</label><textarea id="ex_note" name="note" rows="3" style="min-height:80px;"><?php echo platform_e($examRow['note'] ?? ''); ?></textarea></div>
                <div class="admin-form-actions"><button type="submit" class="btn btn-primary">Sınavı Kaydet</button></div>
              </form>
            </details>
            <div class="card admin-card" style="padding:0;overflow:hidden;">
              <div class="admin-table-wrap">
                <table class="admin-table admin-subtable">
                  <thead><tr><th>Sınav</th><th>Ders</th><th>Tarih</th><th>Puan</th><th>Hedef</th><th>Başarı</th><th style="text-align:right;">İşlemler</th></tr></thead>
                  <tbody>
                    <?php if (!$exams): ?><tr><td colspan="7" class="admin-table-empty">Henüz sınav kaydı yok.</td></tr><?php endif; ?>
                    <?php foreach ($exams as $e): ?>
                      <?php $pct = ($e['score'] !== null && (float) $e['max_score'] > 0) ? round(((float) $e['score'] / (float) $e['max_score']) * 100) : null; ?>
                    <tr>
                      <td class="admin-post-title"><?php echo platform_e($e['exam_name']); ?></td>
                      <td><?php echo platform_e($e['subject_name'] ?: '—'); ?></td>
                      <td><?php echo platform_e($e['exam_date']); ?></td>
                      <td><?php echo platform_e($e['score'] ?? '—'); ?></td>
                      <td><?php echo platform_e($e['target_score'] ?? '—'); ?></td>
                      <td><?php if ($pct !== null): ?><span class="admin-pill admin-pill-<?php echo $pct >= 70 ? 'active' : 'at_risk'; ?>">%<?php echo $pct; ?></span><?php else: ?>—<?php endif; ?></td>
                      <td class="admin-actions">
                        <a href="admin.php?action=student-academics&id=<?php echo $sid; ?>&edit_exam=<?php echo (int) $e['id']; ?>" class="btn btn-secondary btn-mini"><?php echo icon("pencil", 14); ?></a>
                        <a href="<?php echo platform_url('exam-delete', ['id' => (int) $e['id'], 'student_id' => $sid]); ?>" class="btn btn-wa btn-mini" onclick="return confirm('Sınav kaydı silinsin mi?');"><?php echo icon("trash", 14); ?></a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'student-career') {
    $sid = (int) ($_GET['id'] ?? 0);
    $student = $sid > 0 ? platform_get_student($sid) : null;
    if (!$student) { http_response_code(404); echo 'Öğrenci bulunamadı.'; exit; }

    $career = platform_get_career_profile($sid);
    $applications = platform_get_applications($sid);
    $editAppId = (int) ($_GET['edit_application'] ?? 0);
    $appRow = null;
    foreach ($applications as $a) { if ((int) $a['id'] === $editAppId) { $appRow = $a; break; } }
    $appKinds = ['university' => 'Üniversite', 'internship' => 'Staj', 'job' => 'İş', 'other' => 'Diğer'];
    $appStatuses = ['preparing' => 'Hazırlanıyor', 'submitted' => 'Gönderildi', 'interview' => 'Görüşme', 'accepted' => 'Kabul', 'rejected' => 'Reddedildi'];
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Kariyer Takibi | <?php echo platform_e($student['first_name'] . ' ' . $student['last_name']); ?></title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div><span class="eyebrow"><?php echo platform_e($student['student_code']); ?></span><h1>Kariyer Takibi</h1></div>
            <a href="admin.php?action=students" class="btn btn-secondary">← Öğrenciler</a>
          </div>
          <?php platform_student_tabs($sid, 'student-career'); ?>

          <section class="admin-section mt-24">
            <div class="admin-section-head"><h3><?php echo icon("target", 18); ?> Kariyer Profili</h3></div>
            <form method="post" action="admin.php?action=career-save" class="admin-form platform-form">
              <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
              <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
              <div class="admin-form-row">
                <div class="form-field"><label for="ca_uni">Hedef Üniversite</label><input type="text" id="ca_uni" name="target_university" value="<?php echo platform_e($career['target_university']); ?>"></div>
                <div class="form-field"><label for="ca_dep">Hedef Bölüm</label><input type="text" id="ca_dep" name="target_department" value="<?php echo platform_e($career['target_department']); ?>"></div>
              </div>
              <div class="form-field"><label for="ca_role">Hedef Meslek / Rol</label><input type="text" id="ca_role" name="target_role" value="<?php echo platform_e($career['target_role']); ?>"></div>
              <div class="admin-form-row">
                <div class="form-field"><label for="ca_skills">Yetkinlikler <span class="admin-hint">(virgülle ayırın)</span></label><textarea id="ca_skills" name="skills" rows="3" style="min-height:80px;"><?php echo platform_e($career['skills']); ?></textarea></div>
                <div class="form-field"><label for="ca_interests">İlgi Alanları</label><textarea id="ca_interests" name="interests" rows="3" style="min-height:80px;"><?php echo platform_e($career['interests']); ?></textarea></div>
              </div>
              <div class="admin-form-row">
                <div class="form-field"><label for="ca_cv">CV / Portfolyo Yolu</label><input type="text" id="ca_cv" name="cv_path" value="<?php echo platform_e($career['cv_path']); ?>" placeholder="uploads/cv-ogrenci.pdf"></div>
                <div class="form-field"><label for="ca_notes">Notlar</label><textarea id="ca_notes" name="notes" rows="3" style="min-height:80px;"><?php echo platform_e($career['notes']); ?></textarea></div>
              </div>
              <div class="admin-form-actions"><button type="submit" class="btn btn-primary">Kariyer Profilini Kaydet</button></div>
            </form>
          </section>

          <section class="admin-section mt-32">
            <div class="admin-section-head"><h3><?php echo icon("mail", 16); ?> Başvurular</h3><span class="admin-muted"><?php echo count($applications); ?> başvuru</span></div>
            <details class="admin-details" <?php echo $appRow ? 'open' : ''; ?>>
              <summary><?php echo $appRow ? 'Başvuruyu düzenle' : '＋ Yeni başvuru ekle'; ?></summary>
              <form method="post" action="admin.php?action=application-save" class="admin-form platform-form">
                <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                <?php if ($appRow): ?><input type="hidden" name="id" value="<?php echo (int) $appRow['id']; ?>"><?php endif; ?>
                <div class="admin-form-row">
                  <div class="form-field"><label for="ap_kind">Tür</label><select id="ap_kind" name="kind">
                    <?php foreach ($appKinds as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo ($appRow['kind'] ?? 'university') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?>
                  </select></div>
                  <div class="form-field"><label for="ap_status">Durum</label><select id="ap_status" name="status">
                    <?php foreach ($appStatuses as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo ($appRow['status'] ?? 'preparing') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?>
                  </select></div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="ap_org">Kurum *</label><input type="text" id="ap_org" name="organization" required value="<?php echo platform_e($appRow['organization'] ?? ''); ?>"></div>
                  <div class="form-field"><label for="ap_pos">Pozisyon / Program</label><input type="text" id="ap_pos" name="position" value="<?php echo platform_e($appRow['position'] ?? ''); ?>"></div>
                </div>
                <div class="form-field"><label for="ap_deadline">Son Tarih</label><input type="date" id="ap_deadline" name="deadline" value="<?php echo platform_e($appRow['deadline'] ?? ''); ?>"></div>
                <div class="form-field"><label for="ap_notes">Notlar</label><textarea id="ap_notes" name="notes" rows="3" style="min-height:80px;"><?php echo platform_e($appRow['notes'] ?? ''); ?></textarea></div>
                <div class="admin-form-actions"><button type="submit" class="btn btn-primary">Başvuruyu Kaydet</button></div>
              </form>
            </details>
            <div class="card admin-card" style="padding:0;overflow:hidden;">
              <div class="admin-table-wrap">
                <table class="admin-table admin-subtable">
                  <thead><tr><th>Kurum</th><th>Tür</th><th>Pozisyon</th><th>Son Tarih</th><th>Durum</th><th style="text-align:right;">İşlemler</th></tr></thead>
                  <tbody>
                    <?php if (!$applications): ?><tr><td colspan="6" class="admin-table-empty">Henüz başvuru eklenmedi.</td></tr><?php endif; ?>
                    <?php foreach ($applications as $a): ?>
                    <tr>
                      <td class="admin-post-title"><?php echo platform_e($a['organization']); ?></td>
                      <td><?php echo platform_e(platform_label($appKinds, $a['kind'])); ?></td>
                      <td><?php echo platform_e($a['position'] ?: '—'); ?></td>
                      <td><?php echo platform_e($a['deadline'] ?: '—'); ?></td>
                      <td><span class="admin-pill admin-pill-<?php echo $a['status'] === 'accepted' ? 'active' : ($a['status'] === 'rejected' ? 'at_risk' : 'pending'); ?>"><?php echo platform_e(platform_label($appStatuses, $a['status'])); ?></span></td>
                      <td class="admin-actions">
                        <a href="admin.php?action=student-career&id=<?php echo $sid; ?>&edit_application=<?php echo (int) $a['id']; ?>" class="btn btn-secondary btn-mini"><?php echo icon("pencil", 14); ?></a>
                        <a href="<?php echo platform_url('application-delete', ['id' => (int) $a['id'], 'student_id' => $sid]); ?>" class="btn btn-wa btn-mini" onclick="return confirm('Başvuru silinsin mi?');"><?php echo icon("trash", 14); ?></a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'student-risk') {
    $sid = (int) ($_GET['id'] ?? 0);
    $student = $sid > 0 ? platform_get_student($sid) : null;
    if (!$student) { http_response_code(404); echo 'Öğrenci bulunamadı.'; exit; }

    $flags = platform_get_risk_flags($sid);
    $interventions = platform_get_interventions($sid);
    $editInterventionId = (int) ($_GET['edit_intervention'] ?? 0);
    $interventionRow = null;
    foreach ($interventions as $i) { if ((int) $i['id'] === $editInterventionId) { $interventionRow = $i; break; } }
    $ruleLabels = [
        'missed_sessions' => 'İptal / gelmeme', 'overdue_actions' => 'Geciken ödev',
        'no_contact' => 'İletişimsizlik', 'low_mood' => 'Düşük ruh hali',
        'exam_drop' => 'Sınav performansı',
    ];
    $severityLabels = ['low' => 'Düşük', 'medium' => 'Orta', 'high' => 'Yüksek'];
    $statusLabels = ['open' => 'Açık', 'reviewed' => 'İncelendi', 'resolved' => 'Çözüldü'];
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Risk Takibi | <?php echo platform_e($student['first_name'] . ' ' . $student['last_name']); ?></title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div><span class="eyebrow"><?php echo platform_e($student['student_code']); ?></span><h1>Risk Takibi</h1></div>
            <div class="btn-group">
              <a href="admin.php?action=students" class="btn btn-secondary">← Öğrenciler</a>
              <a href="<?php echo platform_url('risk-evaluate', ['id' => $sid]); ?>" class="btn btn-primary"><?php echo icon("search", 16); ?> Risk Analizini Çalıştır</a>
            </div>
          </div>
          <?php platform_student_tabs($sid, 'student-risk'); ?>

          <div class="admin-risk-note mt-24">
            <strong>Not:</strong> Sistem yalnızca kural bazlı öneri üretir. Risk seviyesi ve öğrenci durumu kararını koç verir. Aynı uyarı 7 gün içinde tekrar oluşturulmaz.
          </div>

          <section class="admin-section mt-24">
            <div class="admin-section-head"><h3>Öğrenci Durumu</h3></div>
            <form method="post" action="admin.php?action=student-status" class="admin-inline-form">
              <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
              <input type="hidden" name="id" value="<?php echo $sid; ?>">
              <label>Durum:
                <select name="status">
                  <?php foreach (platform_student_statuses() as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $student['status'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?>
                </select>
              </label>
              <button type="submit" class="btn btn-primary btn-mini">Kaydet</button>
              <span class="admin-muted">Mevcut risk: <?php echo platform_e(platform_risk_levels()[$student['risk_level']] ?? $student['risk_level']); ?></span>
            </form>
          </section>

          <section class="admin-section mt-32">
            <div class="admin-section-head"><h3><?php echo icon("alert", 16); ?>️ Risk Uyarıları</h3><span class="admin-muted"><?php echo count(array_filter($flags, function ($f) { return $f['status'] === 'open'; })); ?> açık</span></div>
            <div class="card admin-card" style="padding:0;overflow:hidden;">
              <div class="admin-table-wrap">
                <table class="admin-table admin-subtable">
                  <thead><tr><th>Kural</th><th>Önem</th><th>Açıklama</th><th>Tarih</th><th>Durum</th><th style="text-align:right;">İşlemler</th></tr></thead>
                  <tbody>
                    <?php if (!$flags): ?><tr><td colspan="6" class="admin-table-empty">Henüz risk uyarısı yok. “Risk Analizini Çalıştır” ile kontrol edebilirsiniz.</td></tr><?php endif; ?>
                    <?php foreach ($flags as $f): ?>
                    <tr>
                      <td class="admin-post-title"><?php echo platform_e(platform_label($ruleLabels, $f['rule'])); ?></td>
                      <td><span class="admin-pill admin-pill-risk-<?php echo platform_e($f['severity']); ?>"><?php echo platform_e(platform_label($severityLabels, $f['severity'])); ?></span></td>
                      <td class="admin-note-preview"><?php echo platform_e($f['message']); ?></td>
                      <td><?php echo platform_e(substr((string) $f['triggered_at'], 0, 16)); ?></td>
                      <td><span class="admin-pill admin-pill-<?php echo $f['status'] === 'open' ? 'at_risk' : ($f['status'] === 'resolved' ? 'active' : 'pending'); ?>"><?php echo platform_e(platform_label($statusLabels, $f['status'])); ?></span></td>
                      <td class="admin-actions">
                        <?php if ($f['status'] !== 'reviewed' && $f['status'] !== 'resolved'): ?>
                          <form method="post" action="admin.php?action=risk-status" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                            <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                            <input type="hidden" name="id" value="<?php echo (int) $f['id']; ?>">
                            <input type="hidden" name="status" value="reviewed">
                            <button type="submit" class="btn btn-secondary btn-mini">İncele</button>
                          </form>
                        <?php endif; ?>
                        <?php if ($f['status'] !== 'resolved'): ?>
                          <form method="post" action="admin.php?action=risk-status" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                            <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                            <input type="hidden" name="id" value="<?php echo (int) $f['id']; ?>">
                            <input type="hidden" name="status" value="resolved">
                            <input type="hidden" name="resolution_note" value="Koç tarafından kapatıldı">
                            <button type="submit" class="btn btn-primary btn-mini">Çöz</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <section class="admin-section mt-32">
            <div class="admin-section-head"><h3><?php echo icon("wrench", 16); ?> Müdahale Planı</h3></div>
            <details class="admin-details" <?php echo $interventionRow ? 'open' : ''; ?>>
              <summary><?php echo $interventionRow ? 'Müdahaleyi düzenle' : '＋ Yeni müdahale ekle'; ?></summary>
              <form method="post" action="admin.php?action=intervention-save" class="admin-form platform-form">
                <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
                <input type="hidden" name="student_id" value="<?php echo $sid; ?>">
                <?php if ($interventionRow): ?><input type="hidden" name="id" value="<?php echo (int) $interventionRow['id']; ?>"><?php endif; ?>
                <div class="form-field"><label for="iv_action">Yapılacak Aksiyon *</label><input type="text" id="iv_action" name="action" required value="<?php echo platform_e($interventionRow['action'] ?? ''); ?>" placeholder="Veli ile görüşme yapılacak"></div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="iv_flag">Bağlı Uyarı</label><select id="iv_flag" name="flag_id"><option value="0">Yok</option>
                    <?php foreach ($flags as $f): ?><option value="<?php echo (int) $f['id']; ?>" <?php echo $interventionRow && (int) $interventionRow['flag_id'] === (int) $f['id'] ? 'selected' : ''; ?>><?php echo platform_e(platform_label($ruleLabels, $f['rule'])); ?></option><?php endforeach; ?>
                  </select></div>
                  <div class="form-field"><label for="iv_owner">Sorumlu</label><input type="text" id="iv_owner" name="owner" value="<?php echo platform_e($interventionRow['owner'] ?? 'Gonca LÖK'); ?>"></div>
                </div>
                <div class="admin-form-row">
                  <div class="form-field"><label for="iv_due">Termin</label><input type="date" id="iv_due" name="due_date" value="<?php echo platform_e($interventionRow['due_date'] ?? ''); ?>"></div>
                  <div class="form-field"><label for="iv_status">Durum</label><select id="iv_status" name="status">
                    <option value="open" <?php echo ($interventionRow['status'] ?? 'open') === 'open' ? 'selected' : ''; ?>>Açık</option>
                    <option value="done" <?php echo ($interventionRow['status'] ?? '') === 'done' ? 'selected' : ''; ?>>Tamamlandı</option>
                  </select></div>
                </div>
                <div class="admin-form-actions"><button type="submit" class="btn btn-primary">Müdahaleyi Kaydet</button></div>
              </form>
            </details>
            <div class="card admin-card" style="padding:0;overflow:hidden;">
              <div class="admin-table-wrap">
                <table class="admin-table admin-subtable">
                  <thead><tr><th>Aksiyon</th><th>Bağlı Uyarı</th><th>Sorumlu</th><th>Termin</th><th>Durum</th><th style="text-align:right;">İşlemler</th></tr></thead>
                  <tbody>
                    <?php if (!$interventions): ?><tr><td colspan="6" class="admin-table-empty">Henüz müdahale planı yok.</td></tr><?php endif; ?>
                    <?php foreach ($interventions as $i): ?>
                    <tr>
                      <td class="admin-post-title"><?php echo platform_e($i['action']); ?></td>
                      <td><?php echo platform_e($i['flag_message'] ?: '—'); ?></td>
                      <td><?php echo platform_e($i['owner'] ?: '—'); ?></td>
                      <td><?php echo platform_e($i['due_date'] ?: '—'); ?></td>
                      <td><span class="admin-pill admin-pill-<?php echo $i['status'] === 'done' ? 'active' : 'pending'; ?>"><?php echo $i['status'] === 'done' ? 'Tamamlandı' : 'Açık'; ?></span></td>
                      <td class="admin-actions">
                        <a href="<?php echo platform_url('intervention-status', ['id' => (int) $i['id'], 'student_id' => $sid, 'to' => $i['status'] === 'done' ? 'open' : 'done']); ?>" class="btn btn-secondary btn-mini"><?php echo $i['status'] === 'done' ? 'Geri aç' : 'Bitir'; ?></a>
                        <a href="admin.php?action=student-risk&id=<?php echo $sid; ?>&edit_intervention=<?php echo (int) $i['id']; ?>" class="btn btn-secondary btn-mini"><?php echo icon("pencil", 14); ?></a>
                        <a href="<?php echo platform_url('intervention-delete', ['id' => (int) $i['id'], 'student_id' => $sid]); ?>" class="btn btn-wa btn-mini" onclick="return confirm('Müdahale silinsin mi?');"><?php echo icon("trash", 14); ?></a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'reports') {
    [$from, $to] = platform_report_period();
    $students = platform_get_students();
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Raporlar | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div><span class="eyebrow">Raporlama</span><h1>PDF Raporları</h1></div>
            <a href="<?php echo platform_url('report-overview-pdf'); ?>" class="btn btn-primary"><?php echo icon("file-text", 16); ?> Genel Program Raporu</a>
          </div>

          <section class="admin-section mt-24">
            <div class="admin-section-head"><h3>Dönem Filtresi</h3></div>
            <form method="get" action="admin.php" class="admin-inline-form">
              <input type="hidden" name="action" value="reports">
              <label>Başlangıç: <input type="date" name="from" value="<?php echo platform_e($from); ?>"></label>
              <label>Bitiş: <input type="date" name="to" value="<?php echo platform_e($to); ?>"></label>
              <button type="submit" class="btn btn-primary btn-mini">Uygula</button>
              <a href="admin.php?action=reports" class="btn btn-secondary btn-mini">Temizle</a>
            </form>
            <p class="admin-muted mt-16">Seçilen dönem seans, çalışma, sınav ve başvuru kayıtlarını filtreler. Hedef ve ödevler tüm durumlarıyla listelenir.</p>
          </section>

          <div class="card admin-card mt-24" style="padding:0;overflow:hidden;">
            <div class="admin-table-wrap">
              <table class="admin-table admin-subtable">
                <thead><tr><th>Öğrenci</th><th>Durum</th><th>Koç Raporu</th><th>Paylaşılan Rapor</th></tr></thead>
                <tbody>
                  <?php if (!$students): ?><tr><td colspan="4" class="admin-table-empty">Öğrenci bulunamadı.</td></tr><?php endif; ?>
                  <?php foreach ($students as $s): ?>
                  <tr>
                    <td class="admin-post-title"><?php echo platform_e($s['student_code'] . ' — ' . $s['first_name'] . ' ' . $s['last_name']); ?></td>
                    <td><span class="admin-pill admin-pill-<?php echo platform_e($s['status']); ?>"><?php echo platform_e(platform_student_statuses()[$s['status']] ?? $s['status']); ?></span></td>
                    <td><a href="<?php echo platform_url('report-pdf', ['id' => (int) $s['id'], 'scope' => 'coach'] + platform_report_period_params()); ?>" class="btn btn-secondary btn-mini"><?php echo icon("file-text", 16); ?> İndir</a></td>
                    <td><a href="<?php echo platform_url('report-pdf', ['id' => (int) $s['id'], 'scope' => 'shared'] + platform_report_period_params()); ?>" class="btn btn-secondary btn-mini"><?php echo icon("file-text", 16); ?> İndir</a></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'notifications') {
    $notifications = platform_get_notifications(0, 200);
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Bildirimler | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container">
          <div class="admin-page-head">
            <div><span class="eyebrow">Bildirim Merkezi</span><h1>Bildirimler</h1></div>
          </div>
          <div class="admin-risk-note mt-24">
            Varsayılan olarak e-posta gönderimi kapalıdır (<code>MAIL_ENABLED = false</code>). Tüm denemeler burada kayıt altına alınır; SMTP hazır olduğunda <code>config.php</code> üzerinden açılabilir.
          </div>
          <div class="card admin-card mt-24" style="padding:0;overflow:hidden;">
            <div class="admin-table-wrap">
              <table class="admin-table admin-subtable">
                <thead><tr><th>Tarih</th><th>Öğrenci</th><th>Tür</th><th>Alıcı</th><th>Konu</th><th>Durum</th></tr></thead>
                <tbody>
                  <?php if (!$notifications): ?><tr><td colspan="6" class="admin-table-empty">Henüz bildirim kaydı yok.</td></tr><?php endif; ?>
                  <?php foreach ($notifications as $n): ?>
                  <tr>
                    <td><?php echo platform_e(substr((string) $n['created_at'], 0, 16)); ?></td>
                    <td><?php echo platform_e(trim((string) ($n['first_name'] ?? '') . ' ' . (string) ($n['last_name'] ?? '')) ?: '—'); ?></td>
                    <td><?php echo platform_e($n['kind']); ?></td>
                    <td><?php echo platform_e($n['recipient'] ?: '—'); ?></td>
                    <td class="admin-note-preview"><?php echo platform_e($n['subject']); ?></td>
                    <td><span class="admin-pill admin-pill-<?php echo $n['status'] === 'sent' ? 'active' : ($n['status'] === 'failed' ? 'at_risk' : 'pending'); ?>"><?php echo platform_e($n['status']); ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} elseif ($action === 'password') {
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex,nofollow">
      <title>Şifre Değiştir | Gonca LÖK</title>

      <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="assets/css/style.css?v=18">
      <link rel="stylesheet" href="assets/css/admin.css?v=18">

    </head>
    <body class="admin-body" data-page="admin">
      <?php require_once __DIR__ . '/partials/admin-header.php'; ?>
      <main id="icerik" class="admin-main">
        <div class="container" style="max-width:520px;">
          <div class="admin-page-head">
            <div><span class="eyebrow">Hesabınız</span><h1>Şifre Değiştir</h1></div>
          </div>
          <form method="post" action="admin.php?action=password" class="admin-form mt-24">
            <input type="hidden" name="csrf" value="<?php echo platform_e(platform_csrf_token()); ?>">
            <div class="form-field" style="margin-bottom:14px;">
              <label for="current_password">Mevcut Şifre *</label>
              <input type="password" id="current_password" name="current_password" required autocomplete="current-password" style="min-height:48px;">
            </div>
            <div class="form-field" style="margin-bottom:14px;">
              <label for="new_password">Yeni Şifre (en az 12 karakter) *</label>
              <input type="password" id="new_password" name="new_password" required minlength="12" autocomplete="new-password" style="min-height:48px;">
            </div>
            <div class="form-field" style="margin-bottom:18px;">
              <label for="new_password_confirm">Yeni Şifre (Tekrar) *</label>
              <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="12" autocomplete="new-password" style="min-height:48px;">
            </div>
            <button type="submit" class="btn btn-primary">Şifreyi Güncelle</button>
          </form>
        </div>
      </main>
      </div>
      <script src="assets/js/site.js?v=18"></script>
    </body>
    </html>
    <?php
} else {
    http_response_code(404);
    echo 'Sayfa bulunamadı.';
}
?>
