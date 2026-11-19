<?php
// ============================================================
// GONCA LÖK — Koçluk Platformu veri katmanı (çok organizasyonlu)
// Mevcut db.php üzerine eklenir; mevcut randevu akışını bozmaz.
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function platform_db_column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column"
    );
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function platform_csrf_token(): string
{
    if (empty($_SESSION['platform_csrf'])) {
        $_SESSION['platform_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['platform_csrf'];
}

function platform_csrf_valid(mixed $token): bool
{
    $expected = (string) ($_SESSION['platform_csrf'] ?? '');
    return is_string($token) && $expected !== '' && $token !== '' && hash_equals($expected, $token);
}

function platform_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Denetim kaydı: varsa oturumdaki kullanıcının adı/org'u kullanılır.
function platform_actor(): string
{
    $user = current_user();
    return $user ? ((string) $user['name'] . ($user['role'] ? ' (' . $user['role'] . ')' : '')) : 'Sistem';
}

// Tabloları idempotent olarak oluşturur (geliştirme); üretilen DB için schema.sql/migrate.php kullanılır.
// İstek başına en fazla bir kez çalışır.
function platform_db_ensure_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db_ensure_appointments_table();
    $org = current_org_id();

    $tables = [
        "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_code VARCHAR(50) NOT NULL UNIQUE,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            email VARCHAR(255) NULL,
            birth_date DATE NULL,
            school VARCHAR(255) NULL,
            grade_level VARCHAR(100) NULL,
            coaching_type VARCHAR(100) NOT NULL DEFAULT 'Öğrenci Koçluğu',
            status ENUM('active','at_risk','passive','graduate') NOT NULL DEFAULT 'active',
            risk_level ENUM('low','medium','high') NOT NULL DEFAULT 'low',
            guardian_name VARCHAR(150) NULL,
            guardian_phone VARCHAR(50) NULL,
            guardian_email VARCHAR(255) NULL,
            notes TEXT NULL,
            consent_at DATETIME NULL,
            consent_version VARCHAR(50) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_students_status (organization_id, status),
            KEY idx_students_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS session_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            name VARCHAR(150) NOT NULL,
            type VARCHAR(100) NOT NULL DEFAULT 'Düzenli Seans',
            fields LONGTEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_session_templates_org (organization_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            appointment_id INT NULL,
            template_id INT NULL,
            session_date DATE NOT NULL,
            session_time TIME NOT NULL,
            session_type VARCHAR(100) NOT NULL DEFAULT 'Düzenli Seans',
            status ENUM('planned','completed','cancelled','no_show') NOT NULL DEFAULT 'planned',
            duration_min INT NOT NULL DEFAULT 60,
            mood_before TINYINT NULL,
            mood_after TINYINT NULL,
            shared_note TEXT NULL,
            private_note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_sessions_student_date (organization_id, student_id, session_date),
            KEY idx_sessions_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS goals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            session_id INT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'Akademik',
            description TEXT NULL,
            target_date DATE NULL,
            status ENUM('active','completed','paused') NOT NULL DEFAULT 'active',
            progress TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_goals_student (organization_id, student_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS goal_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            goal_id INT NOT NULL,
            value DECIMAL(10,2) NOT NULL,
            unit VARCHAR(50) NULL,
            note VARCHAR(255) NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_metrics_goal (goal_id, recorded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS actions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            session_id INT NULL,
            goal_id INT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            due_date DATE NULL,
            status ENUM('open','in_progress','done','overdue') NOT NULL DEFAULT 'open',
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_actions_student (organization_id, student_id, status),
            KEY idx_actions_due (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS consents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            consent_type VARCHAR(100) NOT NULL DEFAULT 'kvkk',
            text_version VARCHAR(50) NOT NULL DEFAULT '1.0',
            granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip VARCHAR(64) NULL,
            withdrawn_at DATETIME NULL,
            KEY idx_consents_student (organization_id, student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            actor_name VARCHAR(150) NOT NULL DEFAULT 'Sistem',
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(100) NOT NULL,
            entity_id INT NULL,
            details TEXT NULL,
            ip VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            teacher VARCHAR(150) NULL,
            term VARCHAR(100) NULL,
            target_grade VARCHAR(50) NULL,
            current_grade VARCHAR(50) NULL,
            status ENUM('active','completed','paused') NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_subjects_student (organization_id, student_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS lessons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            subject_id INT NULL,
            lesson_date DATE NOT NULL,
            topic VARCHAR(255) NOT NULL,
            duration_min INT NOT NULL DEFAULT 60,
            status ENUM('planned','done','cancelled') NOT NULL DEFAULT 'planned',
            coach_note TEXT NULL,
            student_feedback TEXT NULL,
            homework TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_lessons_student_date (organization_id, student_id, lesson_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS exams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            subject_id INT NULL,
            exam_name VARCHAR(255) NOT NULL,
            exam_date DATE NOT NULL,
            score DECIMAL(6,2) NULL,
            target_score DECIMAL(6,2) NULL,
            max_score DECIMAL(6,2) NOT NULL DEFAULT 100,
            note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_exams_student_date (organization_id, student_id, exam_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS career_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL UNIQUE,
            target_university VARCHAR(255) NULL,
            target_department VARCHAR(255) NULL,
            target_role VARCHAR(255) NULL,
            skills TEXT NULL,
            interests TEXT NULL,
            cv_path VARCHAR(255) NULL,
            notes TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_career_org (organization_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            kind ENUM('university','internship','job','other') NOT NULL DEFAULT 'university',
            organization VARCHAR(255) NOT NULL,
            position VARCHAR(255) NULL,
            deadline DATE NULL,
            status ENUM('preparing','submitted','interview','accepted','rejected') NOT NULL DEFAULT 'preparing',
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_applications_student (organization_id, student_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS risk_flags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            rule VARCHAR(100) NOT NULL,
            severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
            message TEXT NOT NULL,
            triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('open','reviewed','resolved') NOT NULL DEFAULT 'open',
            resolution_note TEXT NULL,
            resolved_at DATETIME NULL,
            KEY idx_risk_student_status (organization_id, student_id, status),
            KEY idx_risk_rule_time (student_id, rule, triggered_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS interventions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            flag_id INT NULL,
            action TEXT NOT NULL,
            owner VARCHAR(150) NULL,
            due_date DATE NULL,
            status ENUM('open','done') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_interventions_student (organization_id, student_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS portal_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            role ENUM('student','guardian') NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_portal_token (token_hash),
            KEY idx_portal_tokens_student_role (organization_id, student_id, role, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS notification_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            student_id INT NULL,
            kind VARCHAR(100) NOT NULL,
            recipient VARCHAR(255) NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NULL,
            status ENUM('sent','failed','skipped') NOT NULL DEFAULT 'skipped',
            error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_notifications_student (organization_id, student_id, created_at),
            KEY idx_notifications_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($tables as $sql) {
        db()->exec($sql);
    }

    if (!platform_db_column_exists('appointments', 'student_id')) {
        db()->exec(
            "ALTER TABLE appointments
             ADD COLUMN student_id INT NULL AFTER status,
             ADD KEY idx_appt_student (student_id)"
        );
    }

    $templateStmt = db()->prepare("SELECT COUNT(*) FROM session_templates WHERE organization_id = :org");
    $templateStmt->execute([':org' => $org]);
    $templateCount = (int) $templateStmt->fetchColumn();
    if ($templateCount === 0) {
        $stmt = db()->prepare(
            "INSERT INTO session_templates (organization_id, name, type, fields) VALUES
             (:org1, :n1, :t1, :f1), (:org2, :n2, :t2, :f2), (:org3, :n3, :t3, :f3)"
        );
        $stmt->execute([
            ':org1' => $org, ':n1' => 'Düzenli Seans Notu', ':t1' => 'Düzenli Seans',
            ':f1' => "Bu haftaki durum\nHedefe göre ilerleme\nZorlanılan noktalar\nKararlar / içgörüler\nÖdevler ve teslim tarihi\nSonraki seans planı",
            ':org2' => $org, ':n2' => 'İlk Görüşme', ':t2' => 'İlk Görüşme',
            ':f2' => "Talep / beklenti\nEğitim ve kariyer geçmişi\nMevcut zorluklar\nBelirlenen hedefler\nİlk aksiyonlar",
            ':org3' => $org, ':n3' => 'Kariyer Seansı', ':t3' => 'Kariyer Koçluğu',
            ':f3' => "Hedef rol / kurum\nCV ve portfolyo durumu\nBaşvuru süreci\nGüçlü yönler / gelişim alanları\nSonraki adımlar",
        ]);
    }
}

function platform_db_audit(string $action, string $entityType, ?int $entityId = null, string $details = ''): void
{
    try {
        platform_db_ensure_tables();
        $stmt = db()->prepare(
            "INSERT INTO audit_logs (organization_id, actor_name, action, entity_type, entity_id, details, ip)
             VALUES (:org, :actor, :action, :entity, :entity_id, :details, :ip)"
        );
        $stmt->execute([
            ':org' => current_org_id(),
            ':actor' => platform_actor(),
            ':action' => $action,
            ':entity' => $entityType,
            ':entity_id' => $entityId,
            ':details' => $details !== '' ? $details : null,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        app_log('WARNING', 'audit log yazılamadı', ['error' => $e->getMessage(), 'action' => $action]);
    }
}

// ---- Öğrenciler ----

function platform_student_statuses(): array
{
    return [
        'active' => 'Aktif',
        'at_risk' => 'Risk Altında',
        'passive' => 'Pasif',
        'graduate' => 'Mezun',
    ];
}

function platform_risk_levels(): array
{
    return ['low' => 'Düşük', 'medium' => 'Orta', 'high' => 'Yüksek'];
}

function platform_get_students(string $status = '', int $limit = 0, int $offset = 0): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $sql = "SELECT s.*,
              (SELECT COUNT(*) FROM sessions se WHERE se.student_id = s.id) AS session_count,
              (SELECT COUNT(*) FROM goals g WHERE g.student_id = s.id AND g.status = 'active') AS goal_count,
              (SELECT COUNT(*) FROM actions a WHERE a.student_id = s.id AND a.status IN ('open','in_progress','overdue')) AS open_action_count
            FROM students s
            WHERE s.organization_id = :org";
    $params = [':org' => $org];
    if ($status !== '' && array_key_exists($status, platform_student_statuses())) {
        $sql .= " AND s.status = :status";
        $params[':status'] = $status;
    }
    $sql .= " ORDER BY FIELD(s.status, 'at_risk', 'active', 'passive', 'graduate'), s.last_name ASC, s.first_name ASC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function platform_count_students(string $status = ''): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $sql = "SELECT COUNT(*) FROM students WHERE organization_id = :org";
    $params = [':org' => $org];
    if ($status !== '' && array_key_exists($status, platform_student_statuses())) {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function platform_get_student(int $id): ?array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("SELECT * FROM students WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function platform_create_student(array $data): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $year = date('Y');
    $nextStmt = db()->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM students WHERE organization_id = :org");
    $nextStmt->execute([':org' => $org]);
    $next = (int) $nextStmt->fetchColumn();
    $code = 'OG-' . $year . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

    $stmt = db()->prepare(
        "INSERT INTO students
         (organization_id, student_code, first_name, last_name, phone, email, birth_date, school, grade_level,
          coaching_type, status, risk_level, guardian_name, guardian_phone, guardian_email, notes,
          consent_at, consent_version)
         VALUES
         (:org, :code, :first_name, :last_name, :phone, :email, :birth_date, :school, :grade_level,
          :coaching_type, :status, :risk_level, :guardian_name, :guardian_phone, :guardian_email, :notes,
          :consent_at, :consent_version)"
    );
    $stmt->execute([
        ':org' => $org,
        ':code' => $code,
        ':first_name' => mb_substr(trim((string) $data['first_name']), 0, 100),
        ':last_name' => mb_substr(trim((string) $data['last_name']), 0, 100),
        ':phone' => mb_substr(db_normalize_phone((string) $data['phone']), 0, 50),
        ':email' => $data['email'] !== '' ? mb_substr(mb_strtolower(trim((string) $data['email'])), 0, 190) : null,
        ':birth_date' => $data['birth_date'] !== '' ? $data['birth_date'] : null,
        ':school' => $data['school'] !== '' ? mb_substr(trim((string) $data['school']), 0, 255) : null,
        ':grade_level' => $data['grade_level'] !== '' ? mb_substr(trim((string) $data['grade_level']), 0, 100) : null,
        ':coaching_type' => mb_substr(trim((string) $data['coaching_type']), 0, 100),
        ':status' => $data['status'],
        ':risk_level' => $data['risk_level'],
        ':guardian_name' => $data['guardian_name'] !== '' ? mb_substr(trim((string) $data['guardian_name']), 0, 150) : null,
        ':guardian_phone' => $data['guardian_phone'] !== '' ? mb_substr(db_normalize_phone((string) $data['guardian_phone']), 0, 50) : null,
        ':guardian_email' => $data['guardian_email'] !== '' ? mb_substr(mb_strtolower(trim((string) $data['guardian_email'])), 0, 190) : null,
        ':notes' => $data['notes'] !== '' ? mb_substr((string) $data['notes'], 0, 4000) : null,
        ':consent_at' => $data['consent'] ? date('Y-m-d H:i:s') : null,
        ':consent_version' => $data['consent'] ? '1.1' : null,
    ]);
    $id = (int) db()->lastInsertId();

    if ($data['consent']) {
        platform_add_consent($id, 'kvkk', '1.1');
    }
    platform_db_audit('created', 'student', $id, $code . ' — ' . $data['first_name'] . ' ' . $data['last_name']);
    return $id;
}

function platform_update_student(int $id, array $data): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "UPDATE students SET
            first_name = :first_name, last_name = :last_name, phone = :phone, email = :email,
            birth_date = :birth_date, school = :school, grade_level = :grade_level,
            coaching_type = :coaching_type, status = :status, risk_level = :risk_level,
            guardian_name = :guardian_name, guardian_phone = :guardian_phone,
            guardian_email = :guardian_email, notes = :notes
         WHERE id = :id AND organization_id = :org"
    );
    $stmt->execute([
        ':first_name' => mb_substr(trim((string) $data['first_name']), 0, 100),
        ':last_name' => mb_substr(trim((string) $data['last_name']), 0, 100),
        ':phone' => mb_substr(db_normalize_phone((string) $data['phone']), 0, 50),
        ':email' => $data['email'] !== '' ? mb_substr(mb_strtolower(trim((string) $data['email'])), 0, 190) : null,
        ':birth_date' => $data['birth_date'] !== '' ? $data['birth_date'] : null,
        ':school' => $data['school'] !== '' ? mb_substr(trim((string) $data['school']), 0, 255) : null,
        ':grade_level' => $data['grade_level'] !== '' ? mb_substr(trim((string) $data['grade_level']), 0, 100) : null,
        ':coaching_type' => mb_substr(trim((string) $data['coaching_type']), 0, 100),
        ':status' => $data['status'],
        ':risk_level' => $data['risk_level'],
        ':guardian_name' => $data['guardian_name'] !== '' ? mb_substr(trim((string) $data['guardian_name']), 0, 150) : null,
        ':guardian_phone' => $data['guardian_phone'] !== '' ? mb_substr(db_normalize_phone((string) $data['guardian_phone']), 0, 50) : null,
        ':guardian_email' => $data['guardian_email'] !== '' ? mb_substr(mb_strtolower(trim((string) $data['guardian_email'])), 0, 190) : null,
        ':notes' => $data['notes'] !== '' ? mb_substr((string) $data['notes'], 0, 4000) : null,
        ':id' => $id,
        ':org' => $org,
    ]);

    if ($data['consent']) {
        $stmt = db()->prepare("UPDATE students SET consent_at = COALESCE(consent_at, NOW()), consent_version = COALESCE(consent_version, '1.1') WHERE id = :id AND organization_id = :org");
        $stmt->execute([':id' => $id, ':org' => $org]);
        $check = db()->prepare("SELECT COUNT(*) FROM consents WHERE student_id = :id AND organization_id = :org AND withdrawn_at IS NULL");
        $check->execute([':id' => $id, ':org' => $org]);
        if ((int) $check->fetchColumn() === 0) {
            platform_add_consent($id, 'kvkk', '1.1');
        }
    }

    platform_db_audit('updated', 'student', $id, 'Öğrenci bilgileri güncellendi.');
    return true;
}

function platform_set_student_status(int $id, string $status): bool
{
    if (!array_key_exists($status, platform_student_statuses())) {
        return false;
    }
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("UPDATE students SET status = :status WHERE id = :id AND organization_id = :org");
    $stmt->execute([':status' => $status, ':id' => $id, ':org' => $org]);
    platform_db_audit('status_changed', 'student', $id, 'Yeni durum: ' . platform_student_statuses()[$status]);
    return true;
}

function platform_add_consent(int $studentId, string $type = 'kvkk', string $version = '1.1'): void
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "INSERT INTO consents (organization_id, student_id, consent_type, text_version, ip) VALUES (:org, :sid, :type, :version, :ip)"
    );
    $stmt->execute([
        ':org' => $org,
        ':sid' => $studentId,
        ':type' => $type,
        ':version' => $version,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function platform_get_consents(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("SELECT * FROM consents WHERE student_id = :sid AND organization_id = :org ORDER BY granted_at DESC");
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

// ---- Randevu ↔ Öğrenci ----

function platform_get_appointment_student_id(int $appointmentId): ?int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("SELECT student_id FROM appointments WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $appointmentId, ':org' => $org]);
    $value = $stmt->fetchColumn();
    return $value !== false && $value !== null ? (int) $value : null;
}

function platform_link_appointment_student(int $appointmentId, ?int $studentId): void
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("UPDATE appointments SET student_id = :sid WHERE id = :id AND organization_id = :org");
    $stmt->execute([':sid' => $studentId > 0 ? $studentId : null, ':id' => $appointmentId, ':org' => $org]);
    if ($studentId > 0) {
        platform_db_audit('appointment_linked', 'student', $studentId, 'Randevu #' . $appointmentId);
    }
}

function platform_get_appointments_for_student(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT id, name, appointment_date, appointment_time, status, coaching_type
         FROM appointments WHERE student_id = :sid AND organization_id = :org
         ORDER BY appointment_date DESC, appointment_time DESC"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_get_unlinked_appointments(): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT id, name, appointment_date, appointment_time
         FROM appointments
         WHERE student_id IS NULL AND status IN ('pending','confirmed') AND organization_id = :org
         ORDER BY appointment_date ASC, appointment_time ASC"
    );
    $stmt->execute([':org' => $org]);
    return $stmt->fetchAll();
}

// ---- Seanslar ----

function platform_get_templates(): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("SELECT * FROM session_templates WHERE active = 1 AND organization_id = :org ORDER BY id ASC");
    $stmt->execute([':org' => $org]);
    return $stmt->fetchAll();
}

function platform_get_sessions(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT se.*, t.name AS template_name
         FROM sessions se LEFT JOIN session_templates t ON t.id = se.template_id
         WHERE se.student_id = :sid AND se.organization_id = :org
         ORDER BY se.session_date DESC, se.session_time DESC, se.id DESC"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_get_session(int $id): ?array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("SELECT * FROM sessions WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function platform_save_session(int $id, array $data): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $fields = [
        ':student_id' => $data['student_id'],
        ':appointment_id' => $data['appointment_id'] > 0 ? $data['appointment_id'] : null,
        ':template_id' => $data['template_id'] > 0 ? $data['template_id'] : null,
        ':session_date' => $data['session_date'],
        ':session_time' => $data['session_time'],
        ':session_type' => mb_substr((string) $data['session_type'], 0, 100),
        ':status' => in_array($data['status'], ['planned', 'completed', 'cancelled', 'no_show'], true) ? $data['status'] : 'planned',
        ':duration_min' => max(5, min(600, (int) $data['duration_min'])),
        ':mood_before' => $data['mood_before'] !== '' ? max(1, min(10, (int) $data['mood_before'])) : null,
        ':mood_after' => $data['mood_after'] !== '' ? max(1, min(10, (int) $data['mood_after'])) : null,
        ':shared_note' => $data['shared_note'] !== '' ? mb_substr((string) $data['shared_note'], 0, 8000) : null,
        ':private_note' => $data['private_note'] !== '' ? mb_substr((string) $data['private_note'], 0, 8000) : null,
        ':org' => $org,
    ];

    if ($id > 0) {
        $sql = "UPDATE sessions SET student_id = :student_id, appointment_id = :appointment_id,
                template_id = :template_id, session_date = :session_date, session_time = :session_time,
                session_type = :session_type, status = :status, duration_min = :duration_min,
                mood_before = :mood_before, mood_after = :mood_after,
                shared_note = :shared_note, private_note = :private_note
                WHERE id = :id AND organization_id = :org";
        $fields[':id'] = $id;
        db()->prepare($sql)->execute($fields);
        platform_db_audit('updated', 'session', $id, 'Seans güncellendi.');
        return $id;
    }

    $sql = "INSERT INTO sessions (organization_id, student_id, appointment_id, template_id, session_date, session_time,
            session_type, status, duration_min, mood_before, mood_after, shared_note, private_note)
            VALUES (:org, :student_id, :appointment_id, :template_id, :session_date, :session_time,
            :session_type, :status, :duration_min, :mood_before, :mood_after, :shared_note, :private_note)";
    db()->prepare($sql)->execute($fields);
    $newId = (int) db()->lastInsertId();
    platform_db_audit('created', 'session', $newId, 'Yeni seans kaydedildi.');
    return $newId;
}

function platform_delete_session(int $id): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("DELETE FROM sessions WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    platform_db_audit('deleted', 'session', $id, 'Seans silindi.');
    return $stmt->rowCount() > 0;
}

// ---- Hedefler ----

function platform_get_goals(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT * FROM goals WHERE student_id = :sid AND organization_id = :org
         ORDER BY status = 'active' DESC, target_date ASC, id DESC"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_save_goal(int $id, array $data): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $fields = [
        ':student_id' => $data['student_id'],
        ':session_id' => $data['session_id'] > 0 ? $data['session_id'] : null,
        ':title' => mb_substr(trim((string) $data['title']), 0, 255),
        ':category' => mb_substr((string) $data['category'], 0, 100),
        ':description' => $data['description'] !== '' ? mb_substr((string) $data['description'], 0, 4000) : null,
        ':target_date' => $data['target_date'] !== '' ? $data['target_date'] : null,
        ':status' => in_array($data['status'], ['active', 'completed', 'paused'], true) ? $data['status'] : 'active',
        ':progress' => max(0, min(100, (int) $data['progress'])),
        ':org' => $org,
    ];

    if ($id > 0) {
        $fields[':id'] = $id;
        db()->prepare(
            "UPDATE goals SET student_id = :student_id, session_id = :session_id, title = :title,
             category = :category, description = :description, target_date = :target_date,
             status = :status, progress = :progress WHERE id = :id AND organization_id = :org"
        )->execute($fields);
        platform_db_audit('updated', 'goal', $id, 'Hedef güncellendi.');
        return $id;
    }

    db()->prepare(
        "INSERT INTO goals (organization_id, student_id, session_id, title, category, description, target_date, status, progress)
         VALUES (:org, :student_id, :session_id, :title, :category, :description, :target_date, :status, :progress)"
    )->execute($fields);
    $newId = (int) db()->lastInsertId();
    platform_db_audit('created', 'goal', $newId, 'Yeni hedef eklendi.');
    return $newId;
}

function platform_delete_goal(int $id): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("DELETE FROM goals WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    platform_db_audit('deleted', 'goal', $id, 'Hedef silindi.');
    return $stmt->rowCount() > 0;
}

// ---- Ödev / Aksiyon ----

function platform_get_actions(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT a.*, g.title AS goal_title
         FROM actions a LEFT JOIN goals g ON g.id = a.goal_id
         WHERE a.student_id = :sid AND a.organization_id = :org
         ORDER BY FIELD(a.status, 'overdue','open','in_progress','done'), a.due_date ASC, a.id DESC"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_save_action(int $id, array $data): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $status = in_array($data['status'], ['open', 'in_progress', 'done', 'overdue'], true) ? $data['status'] : 'open';
    $fields = [
        ':student_id' => $data['student_id'],
        ':session_id' => $data['session_id'] > 0 ? $data['session_id'] : null,
        ':goal_id' => $data['goal_id'] > 0 ? $data['goal_id'] : null,
        ':title' => mb_substr(trim((string) $data['title']), 0, 255),
        ':description' => $data['description'] !== '' ? mb_substr((string) $data['description'], 0, 4000) : null,
        ':due_date' => $data['due_date'] !== '' ? $data['due_date'] : null,
        ':status' => $status,
        ':completed_at' => $status === 'done' ? date('Y-m-d H:i:s') : null,
        ':org' => $org,
    ];

    if ($id > 0) {
        $fields[':id'] = $id;
        db()->prepare(
            "UPDATE actions SET student_id = :student_id, session_id = :session_id, goal_id = :goal_id,
             title = :title, description = :description, due_date = :due_date,
             status = :status, completed_at = :completed_at WHERE id = :id AND organization_id = :org"
        )->execute($fields);
        platform_db_audit('updated', 'action', $id, 'Ödev güncellendi.');
        return $id;
    }

    db()->prepare(
        "INSERT INTO actions (organization_id, student_id, session_id, goal_id, title, description, due_date, status, completed_at)
         VALUES (:org, :student_id, :session_id, :goal_id, :title, :description, :due_date, :status, :completed_at)"
    )->execute($fields);
    $newId = (int) db()->lastInsertId();
    platform_db_audit('created', 'action', $newId, 'Yeni ödev eklendi.');
    return $newId;
}

function platform_toggle_action(int $id, ?int $orgId = null, ?int $studentId = null): bool
{
    platform_db_ensure_tables();
    $org = $orgId ?? current_org_id();
    $sql = "SELECT status FROM actions WHERE id = :id AND organization_id = :org";
    $params = [':id' => $id, ':org' => $org];
    if ($studentId !== null) {
        $sql .= " AND student_id = :sid";
        $params[':sid'] = $studentId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $current = $stmt->fetchColumn();
    if ($current === false) {
        return false;
    }
    $new = $current === 'done' ? 'open' : 'done';
    $update = db()->prepare(
        "UPDATE actions SET status = :status, completed_at = " . ($new === 'done' ? 'NOW()' : 'NULL') . " WHERE id = :id AND organization_id = :org"
    );
    $update->execute([':status' => $new, ':id' => $id, ':org' => $org]);
    platform_db_audit('status_changed', 'action', $id, 'Yeni durum: ' . $new);
    return true;
}

function platform_delete_action(int $id): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("DELETE FROM actions WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    platform_db_audit('deleted', 'action', $id, 'Ödev silindi.');
    return $stmt->rowCount() > 0;
}

// ---- Dashboard özeti ----

function platform_get_overview(): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $scalar = static function (string $sql) use ($org): int {
        $stmt = db()->prepare($sql);
        $stmt->execute([':org' => $org]);
        return (int) $stmt->fetchColumn();
    };
    return [
        'active_students' => $scalar("SELECT COUNT(*) FROM students WHERE organization_id = :org AND status IN ('active','at_risk')"),
        'risk_students' => $scalar("SELECT COUNT(*) FROM students WHERE organization_id = :org AND (status = 'at_risk' OR risk_level = 'high')"),
        'upcoming_sessions' => $scalar("SELECT COUNT(*) FROM sessions WHERE organization_id = :org AND status = 'planned' AND session_date >= CURDATE()"),
        'open_actions' => $scalar("SELECT COUNT(*) FROM actions WHERE organization_id = :org AND status IN ('open','in_progress','overdue')"),
    ];
}

// ============================================================
// FAZ 3 — Ders / sınav / kariyer / risk
// ============================================================

function platform_label(array $map, string $key): string
{
    return $map[$key] ?? $key;
}

// ---- Dersler ----

function platform_get_subjects(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("SELECT * FROM subjects WHERE student_id = :sid AND organization_id = :org ORDER BY status = 'active' DESC, name ASC");
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_save_subject(int $id, array $d): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $fields = [
        ':student_id' => $d['student_id'],
        ':name' => mb_substr(trim((string) $d['name']), 0, 150),
        ':teacher' => $d['teacher'] !== '' ? mb_substr(trim((string) $d['teacher']), 0, 150) : null,
        ':term' => $d['term'] !== '' ? mb_substr(trim((string) $d['term']), 0, 100) : null,
        ':target_grade' => $d['target_grade'] !== '' ? mb_substr((string) $d['target_grade'], 0, 50) : null,
        ':current_grade' => $d['current_grade'] !== '' ? mb_substr((string) $d['current_grade'], 0, 50) : null,
        ':status' => in_array($d['status'], ['active', 'completed', 'paused'], true) ? $d['status'] : 'active',
        ':notes' => $d['notes'] !== '' ? mb_substr((string) $d['notes'], 0, 4000) : null,
        ':org' => $org,
    ];
    if ($id > 0) {
        $fields[':id'] = $id;
        db()->prepare(
            "UPDATE subjects SET student_id = :student_id, name = :name, teacher = :teacher, term = :term,
             target_grade = :target_grade, current_grade = :current_grade, status = :status, notes = :notes
             WHERE id = :id AND organization_id = :org"
        )->execute($fields);
        platform_db_audit('updated', 'subject', $id, 'Ders güncellendi.');
        return $id;
    }
    db()->prepare(
        "INSERT INTO subjects (organization_id, student_id, name, teacher, term, target_grade, current_grade, status, notes)
         VALUES (:org, :student_id, :name, :teacher, :term, :target_grade, :current_grade, :status, :notes)"
    )->execute($fields);
    $newId = (int) db()->lastInsertId();
    platform_db_audit('created', 'subject', $newId, 'Yeni ders eklendi.');
    return $newId;
}

function platform_delete_subject(int $id): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    db()->prepare("UPDATE lessons SET subject_id = NULL WHERE subject_id = :id AND organization_id = :org")->execute([':id' => $id, ':org' => $org]);
    db()->prepare("UPDATE exams SET subject_id = NULL WHERE subject_id = :id AND organization_id = :org")->execute([':id' => $id, ':org' => $org]);
    $stmt = db()->prepare("DELETE FROM subjects WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    platform_db_audit('deleted', 'subject', $id, 'Ders silindi.');
    return $stmt->rowCount() > 0;
}

// ---- Çalışma / ders seansları ----

function platform_get_lessons(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT l.*, s.name AS subject_name
         FROM lessons l LEFT JOIN subjects s ON s.id = l.subject_id
         WHERE l.student_id = :sid AND l.organization_id = :org ORDER BY l.lesson_date DESC, l.id DESC"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_save_lesson(int $id, array $d): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $fields = [
        ':student_id' => $d['student_id'],
        ':subject_id' => $d['subject_id'] > 0 ? $d['subject_id'] : null,
        ':lesson_date' => $d['lesson_date'],
        ':topic' => mb_substr(trim((string) $d['topic']), 0, 255),
        ':duration_min' => max(15, min(600, (int) $d['duration_min'])),
        ':status' => in_array($d['status'], ['planned', 'done', 'cancelled'], true) ? $d['status'] : 'planned',
        ':coach_note' => $d['coach_note'] !== '' ? mb_substr((string) $d['coach_note'], 0, 4000) : null,
        ':student_feedback' => $d['student_feedback'] !== '' ? mb_substr((string) $d['student_feedback'], 0, 4000) : null,
        ':homework' => $d['homework'] !== '' ? mb_substr((string) $d['homework'], 0, 4000) : null,
        ':org' => $org,
    ];
    if ($id > 0) {
        $fields[':id'] = $id;
        db()->prepare(
            "UPDATE lessons SET student_id = :student_id, subject_id = :subject_id, lesson_date = :lesson_date,
             topic = :topic, duration_min = :duration_min, status = :status, coach_note = :coach_note,
             student_feedback = :student_feedback, homework = :homework WHERE id = :id AND organization_id = :org"
        )->execute($fields);
        platform_db_audit('updated', 'lesson', $id, 'Çalışma kaydı güncellendi.');
        return $id;
    }
    db()->prepare(
        "INSERT INTO lessons (organization_id, student_id, subject_id, lesson_date, topic, duration_min, status, coach_note, student_feedback, homework)
         VALUES (:org, :student_id, :subject_id, :lesson_date, :topic, :duration_min, :status, :coach_note, :student_feedback, :homework)"
    )->execute($fields);
    $newId = (int) db()->lastInsertId();
    platform_db_audit('created', 'lesson', $newId, 'Yeni çalışma kaydı eklendi.');
    return $newId;
}

function platform_delete_lesson(int $id): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("DELETE FROM lessons WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    platform_db_audit('deleted', 'lesson', $id, 'Çalışma kaydı silindi.');
    return $stmt->rowCount() > 0;
}

// ---- Sınavlar ----

function platform_get_exams(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT e.*, s.name AS subject_name
         FROM exams e LEFT JOIN subjects s ON s.id = e.subject_id
         WHERE e.student_id = :sid AND e.organization_id = :org ORDER BY e.exam_date DESC, e.id DESC"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_save_exam(int $id, array $d): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $fields = [
        ':student_id' => $d['student_id'],
        ':subject_id' => $d['subject_id'] > 0 ? $d['subject_id'] : null,
        ':exam_name' => mb_substr(trim((string) $d['exam_name']), 0, 255),
        ':exam_date' => $d['exam_date'],
        ':score' => $d['score'] !== '' ? max(0, min(10000, (float) $d['score'])) : null,
        ':target_score' => $d['target_score'] !== '' ? max(0, min(10000, (float) $d['target_score'])) : null,
        ':max_score' => $d['max_score'] !== '' ? max(1, min(10000, (float) $d['max_score'])) : 100,
        ':note' => $d['note'] !== '' ? mb_substr((string) $d['note'], 0, 4000) : null,
        ':org' => $org,
    ];
    if ($id > 0) {
        $fields[':id'] = $id;
        db()->prepare(
            "UPDATE exams SET student_id = :student_id, subject_id = :subject_id, exam_name = :exam_name,
             exam_date = :exam_date, score = :score, target_score = :target_score, max_score = :max_score,
             note = :note WHERE id = :id AND organization_id = :org"
        )->execute($fields);
        platform_db_audit('updated', 'exam', $id, 'Sınav kaydı güncellendi.');
        return $id;
    }
    db()->prepare(
        "INSERT INTO exams (organization_id, student_id, subject_id, exam_name, exam_date, score, target_score, max_score, note)
         VALUES (:org, :student_id, :subject_id, :exam_name, :exam_date, :score, :target_score, :max_score, :note)"
    )->execute($fields);
    $newId = (int) db()->lastInsertId();
    platform_db_audit('created', 'exam', $newId, 'Yeni sınav kaydı eklendi.');
    return $newId;
}

function platform_delete_exam(int $id): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("DELETE FROM exams WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    platform_db_audit('deleted', 'exam', $id, 'Sınav kaydı silindi.');
    return $stmt->rowCount() > 0;
}

// ---- Kariyer ----

function platform_get_career_profile(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("SELECT * FROM career_profiles WHERE student_id = :sid AND organization_id = :org");
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    $row = $stmt->fetch();
    return $row ?: [
        'student_id' => $studentId, 'target_university' => '', 'target_department' => '',
        'target_role' => '', 'skills' => '', 'interests' => '', 'cv_path' => '', 'notes' => '',
    ];
}

function platform_save_career_profile(int $studentId, array $d): void
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "INSERT INTO career_profiles
         (organization_id, student_id, target_university, target_department, target_role, skills, interests, cv_path, notes)
         VALUES (:org, :sid, :u, :dep, :role, :skills, :interests, :cv, :notes)
         ON DUPLICATE KEY UPDATE
         target_university = :u2, target_department = :dep2, target_role = :role2,
         skills = :skills2, interests = :interests2, cv_path = :cv2, notes = :notes2"
    );
    $values = [
        ':org' => $org,
        ':sid' => $studentId,
        ':u' => $d['target_university'] !== '' ? mb_substr((string) $d['target_university'], 0, 255) : null,
        ':dep' => $d['target_department'] !== '' ? mb_substr((string) $d['target_department'], 0, 255) : null,
        ':role' => $d['target_role'] !== '' ? mb_substr((string) $d['target_role'], 0, 255) : null,
        ':skills' => $d['skills'] !== '' ? mb_substr((string) $d['skills'], 0, 8000) : null,
        ':interests' => $d['interests'] !== '' ? mb_substr((string) $d['interests'], 0, 8000) : null,
        ':cv' => $d['cv_path'] !== '' ? mb_substr((string) $d['cv_path'], 0, 255) : null,
        ':notes' => $d['notes'] !== '' ? mb_substr((string) $d['notes'], 0, 8000) : null,
    ];
    $stmt->execute($values + [
        ':u2' => $values[':u'], ':dep2' => $values[':dep'], ':role2' => $values[':role'],
        ':skills2' => $values[':skills'], ':interests2' => $values[':interests'],
        ':cv2' => $values[':cv'], ':notes2' => $values[':notes'],
    ]);
    platform_db_audit('updated', 'career_profile', $studentId, 'Kariyer profili güncellendi.');
}

function platform_get_applications(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT * FROM applications WHERE student_id = :sid AND organization_id = :org
         ORDER BY FIELD(status, 'preparing','submitted','interview','accepted','rejected'), deadline ASC, id DESC"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_save_application(int $id, array $d): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $fields = [
        ':student_id' => $d['student_id'],
        ':kind' => in_array($d['kind'], ['university', 'internship', 'job', 'other'], true) ? $d['kind'] : 'university',
        ':organization' => mb_substr(trim((string) $d['organization']), 0, 255),
        ':position' => $d['position'] !== '' ? mb_substr((string) $d['position'], 0, 255) : null,
        ':deadline' => $d['deadline'] !== '' ? $d['deadline'] : null,
        ':status' => in_array($d['status'], ['preparing', 'submitted', 'interview', 'accepted', 'rejected'], true) ? $d['status'] : 'preparing',
        ':notes' => $d['notes'] !== '' ? mb_substr((string) $d['notes'], 0, 4000) : null,
        ':org' => $org,
    ];
    if ($id > 0) {
        $fields[':id'] = $id;
        db()->prepare(
            "UPDATE applications SET student_id = :student_id, kind = :kind, organization = :organization,
             position = :position, deadline = :deadline, status = :status, notes = :notes
             WHERE id = :id AND organization_id = :org"
        )->execute($fields);
        platform_db_audit('updated', 'application', $id, 'Başvuru güncellendi.');
        return $id;
    }
    db()->prepare(
        "INSERT INTO applications (organization_id, student_id, kind, organization, position, deadline, status, notes)
         VALUES (:org, :student_id, :kind, :organization, :position, :deadline, :status, :notes)"
    )->execute($fields);
    $newId = (int) db()->lastInsertId();
    platform_db_audit('created', 'application', $newId, 'Yeni başvuru eklendi.');
    return $newId;
}

function platform_delete_application(int $id): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("DELETE FROM applications WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    platform_db_audit('deleted', 'application', $id, 'Başvuru silindi.');
    return $stmt->rowCount() > 0;
}

// ---- Risk uyarıları ----

function platform_get_risk_flags(int $studentId, string $status = ''): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $sql = "SELECT * FROM risk_flags WHERE student_id = :sid AND organization_id = :org";
    $params = [':sid' => $studentId, ':org' => $org];
    if (in_array($status, ['open', 'reviewed', 'resolved'], true)) {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }
    $sql .= " ORDER BY FIELD(status, 'open','reviewed','resolved'), triggered_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function platform_create_risk_flag(int $studentId, string $rule, string $severity, string $message): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $check = db()->prepare(
        "SELECT COUNT(*) FROM risk_flags
         WHERE student_id = :sid AND organization_id = :org AND rule = :rule AND triggered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $check->execute([':sid' => $studentId, ':org' => $org, ':rule' => $rule]);
    if ((int) $check->fetchColumn() > 0) {
        return false;
    }
    $stmt = db()->prepare(
        "INSERT INTO risk_flags (organization_id, student_id, rule, severity, message) VALUES (:org, :sid, :rule, :severity, :message)"
    );
    $stmt->execute([
        ':org' => $org,
        ':sid' => $studentId,
        ':rule' => $rule,
        ':severity' => in_array($severity, ['low', 'medium', 'high'], true) ? $severity : 'medium',
        ':message' => $message,
    ]);
    platform_db_audit('risk_flagged', 'student', $studentId, $rule . ': ' . $message);
    return true;
}

function platform_set_risk_flag_status(int $id, string $status, string $note = ''): bool
{
    if (!in_array($status, ['open', 'reviewed', 'resolved'], true)) {
        return false;
    }
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "UPDATE risk_flags SET status = :status, resolution_note = " . ($note !== '' ? ':note' : 'NULL') . ",
         resolved_at = " . ($status === 'resolved' ? 'NOW()' : 'NULL') . " WHERE id = :id AND organization_id = :org"
    );
    $params = [':status' => $status, ':id' => $id, ':org' => $org];
    if ($note !== '') { $params[':note'] = $note; }
    $stmt->execute($params);
    platform_db_audit('status_changed', 'risk_flag', $id, 'Yeni durum: ' . $status);
    return true;
}

function platform_get_interventions(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT i.*, f.message AS flag_message
         FROM interventions i LEFT JOIN risk_flags f ON f.id = i.flag_id
         WHERE i.student_id = :sid AND i.organization_id = :org
         ORDER BY i.status = 'open' DESC, i.due_date ASC, i.id DESC"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_save_intervention(int $id, array $d): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $fields = [
        ':student_id' => $d['student_id'],
        ':flag_id' => $d['flag_id'] > 0 ? $d['flag_id'] : null,
        ':action' => mb_substr(trim((string) $d['action']), 0, 4000),
        ':owner' => $d['owner'] !== '' ? mb_substr((string) $d['owner'], 0, 150) : 'Gonca LÖK',
        ':due_date' => $d['due_date'] !== '' ? $d['due_date'] : null,
        ':status' => $d['status'] === 'done' ? 'done' : 'open',
        ':org' => $org,
    ];
    if ($id > 0) {
        $fields[':id'] = $id;
        db()->prepare(
            "UPDATE interventions SET student_id = :student_id, flag_id = :flag_id, action = :action,
             owner = :owner, due_date = :due_date, status = :status WHERE id = :id AND organization_id = :org"
        )->execute($fields);
        platform_db_audit('updated', 'intervention', $id, 'Müdahale güncellendi.');
        return $id;
    }
    db()->prepare(
        "INSERT INTO interventions (organization_id, student_id, flag_id, action, owner, due_date, status)
         VALUES (:org, :student_id, :flag_id, :action, :owner, :due_date, :status)"
    )->execute($fields);
    $newId = (int) db()->lastInsertId();
    platform_db_audit('created', 'intervention', $newId, 'Yeni müdahale planı eklendi.');
    return $newId;
}

function platform_set_intervention_status(int $id, string $status): bool
{
    if (!in_array($status, ['open', 'done'], true)) { return false; }
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("UPDATE interventions SET status = :status WHERE id = :id AND organization_id = :org");
    $stmt->execute([':status' => $status, ':id' => $id, ':org' => $org]);
    platform_db_audit('status_changed', 'intervention', $id, 'Yeni durum: ' . $status);
    return true;
}

function platform_delete_intervention(int $id): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("DELETE FROM interventions WHERE id = :id AND organization_id = :org");
    $stmt->execute([':id' => $id, ':org' => $org]);
    platform_db_audit('deleted', 'intervention', $id, 'Müdahale silindi.');
    return $stmt->rowCount() > 0;
}

// Kural bazlı öneri üretir; karar koçta kalır.
function platform_evaluate_student_risk(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $created = [];
    $today = date('Y-m-d');

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM sessions
         WHERE student_id = :sid AND organization_id = :org AND status IN ('cancelled','no_show') AND session_date >= DATE_SUB(:today, INTERVAL 14 DAY)"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org, ':today' => $today]);
    if ((int) $stmt->fetchColumn() >= 2 && platform_create_risk_flag($studentId, 'missed_sessions', 'high', 'Son 14 günde 2 veya daha fazla iptal / gelmeme kaydı var.')) {
        $created[] = 'İptal / gelmeme';
    }

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM actions
         WHERE student_id = :sid AND organization_id = :org AND status IN ('open','in_progress','overdue') AND due_date IS NOT NULL AND due_date < :today"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org, ':today' => $today]);
    if ((int) $stmt->fetchColumn() >= 2 && platform_create_risk_flag($studentId, 'overdue_actions', 'medium', '2 veya daha fazla ödev teslim tarihini geçti.')) {
        $created[] = 'Geciken ödev';
    }

    $stmt = db()->prepare("SELECT MAX(session_date) FROM sessions WHERE student_id = :sid AND organization_id = :org");
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    $lastSession = $stmt->fetchColumn();
    $stmt = db()->prepare("SELECT created_at FROM students WHERE id = :sid AND organization_id = :org");
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    $createdAt = substr((string) $stmt->fetchColumn(), 0, 10);
    $reference = $lastSession ?: $createdAt;
    if ($reference && $reference < date('Y-m-d', strtotime('-21 days')) && platform_create_risk_flag($studentId, 'no_contact', 'medium', '21 günden fazla süredir seans/iletişim kaydı yok.')) {
        $created[] = 'İletişimsizlik';
    }

    $stmt = db()->prepare(
        "SELECT AVG(mood_after) FROM (
           SELECT mood_after FROM sessions WHERE student_id = :sid AND organization_id = :org AND mood_after IS NOT NULL
           ORDER BY session_date DESC, id DESC LIMIT 3
         ) x"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    $avgMood = $stmt->fetchColumn();
    if ($avgMood !== null && (float) $avgMood < 3 && platform_create_risk_flag($studentId, 'low_mood', 'medium', 'Son 3 seansın ortalama ruh hali skoru 3’ün altında.')) {
        $created[] = 'Düşük ruh hali';
    }

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM exams
         WHERE student_id = :sid AND organization_id = :org AND exam_date >= DATE_SUB(:today, INTERVAL 30 DAY)
           AND target_score > 0 AND score < target_score * 0.7"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org, ':today' => $today]);
    if ((int) $stmt->fetchColumn() >= 1 && platform_create_risk_flag($studentId, 'exam_drop', 'high', 'Son 30 günde hedefin %70 altında sınav sonucu var.')) {
        $created[] = 'Sınav performansı';
    }

    return $created;
}

function platform_get_open_risk_count(): int
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("SELECT COUNT(*) FROM risk_flags WHERE status = 'open' AND organization_id = :org");
    $stmt->execute([':org' => $org]);
    return (int) $stmt->fetchColumn();
}

// ============================================================
// FAZ 4 — öğrenci/veli portalı ve bildirimler
// ============================================================

function platform_app_url(string $path = ''): string
{
    $base = trim((string) APP_URL);
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $base = ($https ? 'https://' : 'http://') . ($host ?: 'localhost');
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function portal_role_label(string $role): string
{
    return $role === 'guardian' ? 'Veli' : 'Öğrenci';
}

// Mail gövdesindeki portal bağlantısını log/DB'de maskeler.
function platform_redact_portal_links(string $text): string
{
    return (string) preg_replace('#portal\.php\?token=[A-Za-z0-9_\-]+#', 'portal.php?token=[REDACTED]', $text);
}

function platform_portal_issue_token(int $studentId, string $role, ?int $days = null): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    if (!in_array($role, ['student', 'guardian'], true)) {
        throw new InvalidArgumentException('Geçersiz portal rolü.');
    }
    $student = platform_get_student($studentId);
    if (!$student) {
        throw new InvalidArgumentException('Öğrenci bulunamadı.');
    }
    $days = $days ?? (int) PORTAL_TOKEN_DAYS;
    $days = max(1, min(365, $days));

    db()->prepare(
        "UPDATE portal_tokens SET active = 0 WHERE student_id = :sid AND role = :role AND active = 1 AND organization_id = :org"
    )->execute([':sid' => $studentId, ':role' => $role, ':org' => $org]);

    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
    db()->prepare(
        "INSERT INTO portal_tokens (organization_id, student_id, role, token_hash, expires_at)
         VALUES (:org, :sid, :role, :hash, :expires)"
    )->execute([':org' => $org, ':sid' => $studentId, ':role' => $role, ':hash' => $hash, ':expires' => $expires]);

    platform_db_audit('portal_link_created', 'student', $studentId, portal_role_label($role) . ' portalı bağlantısı oluşturuldu.');

    return [
        'token' => $token,
        'url' => platform_app_url('portal.php?token=' . $token),
        'expires_at' => $expires,
    ];
}

function platform_portal_token_meta(int $studentId, string $role): ?array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT active, created_at, expires_at, last_used_at
         FROM portal_tokens WHERE student_id = :sid AND role = :role AND organization_id = :org
         ORDER BY active DESC, id DESC LIMIT 1"
    );
    $stmt->execute([':sid' => $studentId, ':role' => $role, ':org' => $org]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function platform_portal_revoke_token(int $studentId, string $role): void
{
    platform_db_ensure_tables();
    $org = current_org_id();
    db()->prepare("UPDATE portal_tokens SET active = 0 WHERE student_id = :sid AND role = :role AND organization_id = :org")
        ->execute([':sid' => $studentId, ':role' => $role, ':org' => $org]);
    platform_db_audit('portal_link_revoked', 'student', $studentId, portal_role_label($role) . ' portalı bağlantısı iptal edildi.');
}

function platform_portal_resolve_token(string $token): ?array
{
    platform_db_ensure_tables();
    $token = trim($token);
    if ($token === '') { return null; }
    $hash = hash('sha256', $token);
    // Portal public bağlamında org filtresi yok: hash tek başına yeterince güvenlidir.
    $stmt = db()->prepare(
        "SELECT id, organization_id, student_id, role FROM portal_tokens
         WHERE token_hash = :hash AND active = 1 AND expires_at >= NOW()"
    );
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch();
    if (!$row) {
        app_log('INFO', 'portal token resolve failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        return null;
    }
    db()->prepare("UPDATE portal_tokens SET last_used_at = NOW() WHERE token_hash = :hash")
        ->execute([':hash' => $hash]);
    return [
        'student_id' => (int) $row['student_id'],
        'role' => $row['role'],
        'organization_id' => (int) $row['organization_id'],
    ];
}

function platform_log_notification(
    ?int $studentId,
    string $kind,
    string $recipient,
    string $subject,
    string $body,
    string $status,
    string $error = ''
): int {
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "INSERT INTO notification_log (organization_id, student_id, kind, recipient, subject, body, status, error)
         VALUES (:org, :sid, :kind, :recipient, :subject, :body, :status, :error)"
    );
    $stmt->execute([
        ':org' => $org,
        ':sid' => $studentId,
        ':kind' => substr($kind, 0, 100),
        ':recipient' => $recipient !== '' ? substr($recipient, 0, 255) : null,
        ':subject' => substr($subject, 0, 255),
        ':body' => platform_redact_portal_links($body),
        ':status' => $status,
        ':error' => $error !== '' ? substr($error, 0, 1000) : null,
    ]);
    return (int) db()->lastInsertId();
}

function platform_send_mail(string $to, string $subject, string $body): array
{
    if (!MAIL_ENABLED) {
        return ['status' => 'skipped', 'error' => 'E-posta gönderimi kapalı (MAIL_ENABLED = false).'];
    }
    $to = str_replace(["\r", "\n"], '', $to);
    $subject = str_replace(["\r", "\n"], '', $subject);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'failed', 'error' => 'Geçersiz alıcı adresi.'];
    }
    $headers = [
        'From' => str_replace(["\r", "\n"], '', MAIL_FROM_NAME) . ' <' . str_replace(["\r", "\n"], '', MAIL_FROM) . '>',
        'Reply-To' => str_replace(["\r", "\n"], '', MAIL_FROM),
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    $headerText = '';
    foreach ($headers as $key => $value) { $headerText .= $key . ': ' . $value . "\r\n"; }
    try {
        $ok = @mail($to, $subject, $body, $headerText);
        return $ok
            ? ['status' => 'sent', 'error' => '']
            : ['status' => 'failed', 'error' => 'mail() gönderimi başarısız.'];
    } catch (Throwable $e) {
        app_log('WARNING', 'mail gönderilemedi', ['error' => $e->getMessage()]);
        return ['status' => 'failed', 'error' => $e->getMessage()];
    }
}

function platform_notify_student(
    int $studentId,
    string $kind,
    string $recipient,
    string $subject,
    string $body
): int {
    // Gövde yerine maskelenmiş hali loglanır; gerçek bağlantı yalnızca alıcıya gider.
    $result = platform_send_mail($recipient, $subject, $body);
    return platform_log_notification($studentId, $kind, $recipient, $subject, $body, $result['status'], $result['error']);
}

function platform_get_notifications(int $studentId = 0, int $limit = 100): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $limit = max(1, min(500, $limit));
    if ($studentId > 0) {
        $stmt = db()->prepare("SELECT * FROM notification_log WHERE organization_id = :org AND student_id = :sid ORDER BY id DESC LIMIT {$limit}");
        $stmt->execute([':org' => $org, ':sid' => $studentId]);
        return $stmt->fetchAll();
    }
    $stmt = db()->prepare(
        "SELECT n.*, s.first_name, s.last_name, s.student_code
         FROM notification_log n LEFT JOIN students s ON s.id = n.student_id
         WHERE n.organization_id = :org
         ORDER BY n.id DESC LIMIT {$limit}"
    );
    $stmt->execute([':org' => $org]);
    return $stmt->fetchAll();
}

// Portalda öğrenciye açılabilir veriler — private_note asla dahil edilmez.
function platform_portal_sessions(int $studentId): array
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare(
        "SELECT session_date, session_time, mood_before, mood_after, shared_note, template_name
         FROM (SELECT se.*, t.name AS template_name FROM sessions se
               LEFT JOIN session_templates t ON t.id = se.template_id
               WHERE se.student_id = :sid AND se.organization_id = :org) x
         ORDER BY session_date DESC, session_time DESC"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    return $stmt->fetchAll();
}

function platform_portal_can_open_action(int $studentId, int $actionId): bool
{
    platform_db_ensure_tables();
    $org = current_org_id();
    $stmt = db()->prepare("SELECT COUNT(*) FROM actions WHERE id = :id AND student_id = :sid AND organization_id = :org");
    $stmt->execute([':id' => $actionId, ':sid' => $studentId, ':org' => $org]);
    return (int) $stmt->fetchColumn() > 0;
}

function platform_grant_portal_consent(int $studentId): void
{
    $org = current_org_id();
    platform_add_consent($studentId, 'kvkk', '1.1');
    $stmt = db()->prepare(
        "UPDATE students SET consent_at = COALESCE(consent_at, NOW()), consent_version = COALESCE(consent_version, '1.1')
         WHERE id = :sid AND organization_id = :org"
    );
    $stmt->execute([':sid' => $studentId, ':org' => $org]);
    platform_db_audit('consent_granted', 'student', $studentId, 'KVKK onayı öğrenci portalından verildi.');
}
