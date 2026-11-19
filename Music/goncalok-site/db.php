<?php
// ============================================================
// GONCA LÖK — Blog, randevu ve uygunluk veri katmanı
// (Çok organizasyonludur; public bağlamlarda SITE_ORG_SLUG kullanılır)
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Blog tablosu (idempotent; yeni kurulumda migrate.php kullanılır)
function db_ensure_blog_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            slug VARCHAR(191) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'Öğrenci Koçluğu',
            image VARCHAR(255) NULL,
            status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
            reading_time INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

class AppointmentValidationException extends RuntimeException {}
class AppointmentConflictException extends RuntimeException {}

// Seans kuralları: 1 saatlik seanslar, saat başı, 09:00 - 19:00 (son başlangıç 18:00)
if (!defined('SLOT_START_HOUR')) { define('SLOT_START_HOUR', 9); }
if (!defined('SLOT_LAST_START_HOUR')) { define('SLOT_LAST_START_HOUR', 18); }
if (!defined('SLOT_END_HOUR')) { define('SLOT_END_HOUR', 19); }

function db_slot_hours(): array
{
    return range(SLOT_START_HOUR, SLOT_LAST_START_HOUR);
}

function db_slot_time(int $hour): string
{
    return sprintf('%02d:00:00', $hour);
}

function db_ensure_appointments_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            email VARCHAR(255) NULL,
            coaching_type VARCHAR(100) NOT NULL DEFAULT 'Öğrenci Koçluğu',
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
            student_id INT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_appt_slot (organization_id, appointment_date, appointment_time, status),
            KEY idx_appt_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $indexCheck = db()->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = 'appointments' AND index_name = 'idx_appt_slot'"
    );
    $indexCheck->execute();
    if ((int) $indexCheck->fetchColumn() === 0) {
        db()->exec("CREATE INDEX idx_appt_slot ON appointments (organization_id, appointment_date, appointment_time, status)");
    }
}

// Adminin meşgul olarak işaretlediği saatler
function db_ensure_admin_busy_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS admin_busy_slots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organization_id INT NOT NULL DEFAULT 1,
            busy_date DATE NOT NULL,
            busy_time TIME NOT NULL,
            reason VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_busy_slot (organization_id, busy_date, busy_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function db_normalize_slot(string $date, string $time): array
{
    $dateObj = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        throw new AppointmentValidationException('Lütfen geçerli bir randevu tarihi seçin.');
    }

    $timeObj = DateTime::createFromFormat('!H:i', $time);
    if (!$timeObj) {
        $timeObj = DateTime::createFromFormat('!H:i:s', $time);
    }
    if (!$timeObj) {
        throw new AppointmentValidationException('Lütfen geçerli bir randevu saati seçin.');
    }

    $hour = (int) $timeObj->format('H');
    $minute = (int) $timeObj->format('i');
    $second = (int) $timeObj->format('s');
    if ($minute !== 0 || $second !== 0 || $hour < SLOT_START_HOUR || $hour > SLOT_LAST_START_HOUR) {
        throw new AppointmentValidationException('Randevular yalnızca 09:00 - 18:00 arasındaki saat başlarında alınabilir.');
    }

    return [$dateObj->format('Y-m-d'), $timeObj->format('H:i:s')];
}

function db_normalize_phone(string $phone): string
{
    return preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
}

function db_valid_phone(string $phone): bool
{
    $clean = db_normalize_phone($phone);
    // TR cep/sabit: +90XXXXXXXXXX, 5XXXXXXXXX, 05XXXXXXXXX (10-13 hane)
    return (bool) preg_match('/^(\+90|0090|0)?5?[0-9]{9,10}$/', $clean) && strlen($clean) >= 10 && strlen($clean) <= 13;
}

function db_valid_email(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false && mb_strlen(trim($email)) <= 190;
}

function db_slot_lock_key(string $date, string $time): string
{
    return 'goncalok_appt_' . $date . '_' . str_replace(':', '', substr($time, 0, 5));
}

function db_lock_slot(string $date, string $time): bool
{
    $stmt = db()->prepare('SELECT GET_LOCK(:lock_key, 10)');
    $stmt->execute([':lock_key' => db_slot_lock_key($date, $time)]);
    return (int) $stmt->fetchColumn() === 1;
}

function db_unlock_slot(string $date, string $time): void
{
    $stmt = db()->prepare('SELECT RELEASE_LOCK(:lock_key)');
    $stmt->execute([':lock_key' => db_slot_lock_key($date, $time)]);
}

function db_find_appointment_conflict(string $date, string $time, int $excludeId = 0, ?int $orgId = null): ?array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_appointments_table();
    $sql = "SELECT id, name, appointment_date, appointment_time
            FROM appointments
            WHERE appointment_date = :date
              AND appointment_time = :time
              AND organization_id = :org
              AND status IN ('pending', 'confirmed')";
    if ($excludeId > 0) {
        $sql .= " AND id <> :exclude_id";
    }
    $sql .= " ORDER BY id ASC LIMIT 1";

    $stmt = db()->prepare($sql);
    $params = [':date' => $date, ':time' => $time, ':org' => $orgId];
    if ($excludeId > 0) {
        $params[':exclude_id'] = $excludeId;
    }
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_find_busy_slot(string $date, string $time, ?int $orgId = null): ?array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_admin_busy_table();
    $stmt = db()->prepare(
        "SELECT id, busy_date, busy_time, reason
         FROM admin_busy_slots
         WHERE busy_date = :date AND busy_time = :time AND organization_id = :org
         LIMIT 1"
    );
    $stmt->execute([':date' => $date, ':time' => $time, ':org' => $orgId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_find_slot_conflict(string $date, string $time, int $excludeId = 0, ?int $orgId = null): ?array
{
    $orgId = $orgId ?? current_org_id();
    $busy = db_find_busy_slot($date, $time, $orgId);
    if ($busy) {
        return [
            'source' => 'admin',
            'message' => 'Gonca Hanım bu saati meşgul olarak işaretledi. Lütfen farklı bir saat seçin.',
        ];
    }

    $appointment = db_find_appointment_conflict($date, $time, $excludeId, $orgId);
    if ($appointment) {
        return [
            'source' => 'appointment',
            'message' => 'Bu tarih ve saat için zaten bir randevu talebi var. Lütfen farklı bir saat seçin.',
        ];
    }

    return null;
}

function db_set_busy_slot(string $date, string $time, string $reason = '', ?int $orgId = null): void
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_admin_busy_table();
    $stmt = db()->prepare(
        "INSERT INTO admin_busy_slots (organization_id, busy_date, busy_time, reason)
         VALUES (:org, :date, :time, :reason)
         ON DUPLICATE KEY UPDATE reason = :reason_update"
    );
    $stmt->execute([
        ':org' => $orgId,
        ':date' => $date,
        ':time' => $time,
        ':reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
        ':reason_update' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
    ]);
}

function db_delete_busy_slot(string $date, string $time, ?int $orgId = null): bool
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_admin_busy_table();
    $stmt = db()->prepare("DELETE FROM admin_busy_slots WHERE busy_date = :date AND busy_time = :time AND organization_id = :org");
    $stmt->execute([':date' => $date, ':time' => $time, ':org' => $orgId]);
    return $stmt->rowCount() > 0;
}

function db_get_day_slot_status(string $date, ?int $orgId = null): array
{
    $orgId = $orgId ?? current_org_id();
    [$date] = db_normalize_slot($date, db_slot_time(SLOT_START_HOUR));
    db_ensure_appointments_table();
    db_ensure_admin_busy_table();

    $apptStmt = db()->prepare(
        "SELECT appointment_time
         FROM appointments
         WHERE appointment_date = :date AND organization_id = :org AND status IN ('pending', 'confirmed')"
    );
    $apptStmt->execute([':date' => $date, ':org' => $orgId]);
    $appointments = [];
    foreach ($apptStmt->fetchAll() as $row) {
        $appointments[$row['appointment_time']] = true;
    }

    $busyStmt = db()->prepare("SELECT busy_time FROM admin_busy_slots WHERE busy_date = :date AND organization_id = :org");
    $busyStmt->execute([':date' => $date, ':org' => $orgId]);
    $busy = [];
    foreach ($busyStmt->fetchAll() as $row) {
        $busy[$row['busy_time']] = true;
    }

    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $slots = [];
    foreach (db_slot_hours() as $hour) {
        $time = db_slot_time($hour);
        $label = substr($time, 0, 5);
        $status = 'available';
        $statusLabel = 'Müsait';
        $source = null;

        if ($date < $today || ($date === $today && $now >= $date . ' ' . $time)) {
            $status = 'past';
            $statusLabel = 'Geçmiş';
        } elseif (array_key_exists($time, $busy)) {
            $status = 'busy';
            $statusLabel = 'Kapalı';
            $source = 'admin';
        } elseif (array_key_exists($time, $appointments)) {
            $status = 'booked';
            $statusLabel = 'Dolu';
            $source = 'appointment';
        }

        $slots[] = [
            'time' => $time,
            'label' => $label,
            'status' => $status,
            'status_label' => $statusLabel,
            'available' => $status === 'available',
            'source' => $source,
        ];
    }

    return $slots;
}

// Haftalık tablo: Pazartesi - Pazar, tüm günler ve 09:00 - 18:00 saatleri
function db_get_week_slot_status(string $anchorDate = '', ?int $orgId = null): array
{
    $orgId = $orgId ?? current_org_id();
    if ($anchorDate === '') {
        $anchorDate = date('Y-m-d');
    }
    [$anchorDate] = db_normalize_slot($anchorDate, db_slot_time(SLOT_START_HOUR));

    $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $anchorDate);
    $weekStart = $anchor->modify('-' . ((int) $anchor->format('N') - 1) . ' days');

    $dayNames = [
        1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe',
        5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar',
    ];
    $dayShorts = [
        1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per',
        5 => 'Cum', 6 => 'Cmt', 7 => 'Paz',
    ];
    $months = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
        7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
    ];

    $today = date('Y-m-d');
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $day = $weekStart->modify('+' . $i . ' days');
        $date = $day->format('Y-m-d');
        $dayNumber = (int) $day->format('N');
        $monthName = $months[(int) $day->format('n')];

        $days[] = [
            'date' => $date,
            'day_name' => $dayNames[$dayNumber],
            'day_short' => $dayShorts[$dayNumber],
            'date_label' => $day->format('d') . ' ' . mb_substr($monthName, 0, 3, 'UTF-8'),
            'full_label' => $day->format('d') . ' ' . $monthName . ' ' . $day->format('Y'),
            'is_today' => $date === $today,
            'slots' => db_get_day_slot_status($date, $orgId),
        ];
    }

    return [
        'week_start' => $days[0]['date'],
        'week_end' => $days[6]['date'],
        'days' => $days,
    ];
}

// ---- Blog ----

function db_get_post_stats(?int $orgId = null): array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_blog_table();
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total,
                SUM(status = 'published') AS published,
                SUM(status = 'draft') AS draft,
                COALESCE(SUM(reading_time), 0) AS reading
         FROM blog_posts WHERE organization_id = :org"
    );
    $stmt->execute([':org' => $orgId]);
    $row = $stmt->fetch() ?: ['total' => 0, 'published' => 0, 'draft' => 0, 'reading' => 0];
    return [
        'total' => (int) $row['total'],
        'published' => (int) $row['published'],
        'draft' => (int) $row['draft'],
        'reading' => (int) $row['reading'],
    ];
}

function db_get_published_posts(?int $orgId = null): array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_blog_table();
    $stmt = db()->prepare(
        "SELECT id, slug, title, content, category, image, reading_time, created_at
         FROM blog_posts
         WHERE status = 'published' AND organization_id = :org
         ORDER BY created_at DESC"
    );
    $stmt->execute([':org' => $orgId]);
    return $stmt->fetchAll();
}

function db_get_post_by_id(int $id, ?int $orgId = null): ?array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_blog_table();
    $stmt = db()->prepare(
        "SELECT id, slug, title, content, category, image, status, reading_time, created_at, updated_at
         FROM blog_posts WHERE id = :id AND organization_id = :org"
    );
    $stmt->execute([':id' => $id, ':org' => $orgId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_get_post(string $slug, ?int $orgId = null): ?array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_blog_table();
    $stmt = db()->prepare(
        "SELECT id, slug, title, content, category, image, reading_time, created_at
         FROM blog_posts
         WHERE slug = :slug AND status = 'published' AND organization_id = :org"
    );
    $stmt->execute([':slug' => $slug, ':org' => $orgId]);
    $post = $stmt->fetch();
    return $post ?: null;
}

// Admin paneli: tüm yazılar (yayıt + taslak)
function db_get_all_posts(?int $orgId = null, int $limit = 0, int $offset = 0): array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_blog_table();
    $sql = "SELECT id, slug, title, content, category, image, status, reading_time, created_at, updated_at
            FROM blog_posts
            WHERE organization_id = :org
            ORDER BY updated_at DESC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    }
    $stmt = db()->prepare($sql);
    $stmt->execute([':org' => $orgId]);
    return $stmt->fetchAll();
}

function db_count_all_posts(?int $orgId = null): int
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_blog_table();
    $stmt = db()->prepare('SELECT COUNT(*) FROM blog_posts WHERE organization_id = :org');
    $stmt->execute([':org' => $orgId]);
    return (int) $stmt->fetchColumn();
}

function db_create_post(string $slug, string $title, string $content, string $category, ?string $image, string $status, int $reading_time, ?int $orgId = null): int
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_blog_table();
    $stmt = db()->prepare(
        "INSERT INTO blog_posts (organization_id, slug, title, content, category, image, status, reading_time)
         VALUES (:org, :slug, :title, :content, :category, :image, :status, :reading_time)"
    );
    $stmt->execute([
        ':org' => $orgId,
        ':slug' => $slug,
        ':title' => $title,
        ':content' => $content,
        ':category' => $category,
        ':image' => $image ?: null,
        ':status' => in_array($status, ['draft', 'published'], true) ? $status : 'draft',
        ':reading_time' => max(0, $reading_time),
    ]);
    return (int) db()->lastInsertId();
}

function db_update_post(int $id, string $slug, string $title, string $content, string $category, ?string $image, string $status, int $reading_time, ?int $orgId = null): bool
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_blog_table();
    $stmt = db()->prepare(
        "UPDATE blog_posts
         SET slug = :slug, title = :title, content = :content, category = :category,
             image = :image, status = :status, reading_time = :reading_time
         WHERE id = :id AND organization_id = :org"
    );
    $stmt->execute([
        ':slug' => $slug,
        ':title' => $title,
        ':content' => $content,
        ':category' => $category,
        ':image' => $image ?: null,
        ':status' => in_array($status, ['draft', 'published'], true) ? $status : 'draft',
        ':reading_time' => max(0, $reading_time),
        ':id' => $id,
        ':org' => $orgId,
    ]);
    return $stmt->rowCount() >= 0;
}

function db_delete_post(int $id, ?int $orgId = null): bool
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_blog_table();
    $stmt = db()->prepare('DELETE FROM blog_posts WHERE id = :id AND organization_id = :org');
    $stmt->execute([':id' => $id, ':org' => $orgId]);
    return $stmt->rowCount() > 0;
}

// ---- Randevular ----

function db_get_appointments(?string $status = null, ?int $orgId = null, int $limit = 0, int $offset = 0): array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_appointments_table();
    $sql = "SELECT id, name, phone, email, coaching_type, appointment_date, appointment_time, status, notes, created_at, updated_at, student_id
            FROM appointments
            WHERE organization_id = :org";
    $params = [':org' => $orgId];
    if ($status !== null && in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }
    $sql .= " ORDER BY appointment_date ASC, appointment_time ASC, id ASC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_count_appointments(?string $status = null, ?int $orgId = null): int
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_appointments_table();
    $sql = 'SELECT COUNT(*) FROM appointments WHERE organization_id = :org';
    $params = [':org' => $orgId];
    if ($status !== null && in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
        $sql .= ' AND status = :status';
        $params[':status'] = $status;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function db_get_public_appointments(?int $orgId = null): array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_appointments_table();
    db_ensure_admin_busy_table();

    $rows = [];
    $stmt = db()->prepare(
        "SELECT appointment_date, appointment_time
         FROM appointments
         WHERE status IN ('pending', 'confirmed') AND organization_id = :org AND appointment_date >= CURDATE()
         ORDER BY appointment_date ASC, appointment_time ASC"
    );
    $stmt->execute([':org' => $orgId]);
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'date' => $row['appointment_date'],
            'time' => $row['appointment_time'],
            'label' => 'Dolu',
            'source' => 'appointment',
        ];
    }

    $busyStmt = db()->prepare(
        "SELECT busy_date, busy_time
         FROM admin_busy_slots
         WHERE organization_id = :org AND busy_date >= CURDATE()
         ORDER BY busy_date ASC, busy_time ASC"
    );
    $busyStmt->execute([':org' => $orgId]);
    foreach ($busyStmt->fetchAll() as $row) {
        $rows[] = [
            'date' => $row['busy_date'],
            'time' => $row['busy_time'],
            'label' => 'Kapalı',
            'source' => 'admin',
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        $cmp = strcmp($a['date'], $b['date']);
        return $cmp !== 0 ? $cmp : strcmp($a['time'], $b['time']);
    });

    return $rows;
}

function db_get_appointment(int $id, ?int $orgId = null): ?array
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_appointments_table();
    $stmt = db()->prepare(
        "SELECT id, name, phone, email, coaching_type, appointment_date, appointment_time, status, notes, created_at, updated_at, student_id
         FROM appointments WHERE id = :id AND organization_id = :org"
    );
    $stmt->execute([':id' => $id, ':org' => $orgId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_create_appointment(string $name, string $phone, string $email, string $coaching_type, string $appointment_date, string $appointment_time, string $status, ?string $notes, ?int $orgId = null): int
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_appointments_table();
    [$date, $time] = db_normalize_slot($appointment_date, $appointment_time);

    if ($name === '' || $phone === '') {
        throw new AppointmentValidationException('Ad ve telefon zorunludur.');
    }
    if (!db_valid_phone($phone)) {
        throw new AppointmentValidationException('Lütfen geçerli bir telefon numarası yazın.');
    }
    if ($email !== '' && !db_valid_email($email)) {
        throw new AppointmentValidationException('Lütfen geçerli bir e-posta adresi yazın.');
    }

    if (!db_lock_slot($date, $time)) {
        throw new AppointmentConflictException('Bu saat için işlem sürüyor. Lütfen birkaç saniye sonra tekrar deneyin.');
    }

    try {
        $conflict = db_find_slot_conflict($date, $time, 0, $orgId);
        if ($conflict) {
            throw new AppointmentConflictException($conflict['message']);
        }

        $stmt = db()->prepare(
            "INSERT INTO appointments (organization_id, name, phone, email, coaching_type, appointment_date, appointment_time, status, notes)
             VALUES (:org, :name, :phone, :email, :coaching_type, :appointment_date, :appointment_time, :status, :notes)"
        );
        $stmt->execute([
            ':org' => $orgId,
            ':name' => mb_substr(trim($name), 0, 255),
            ':phone' => mb_substr(db_normalize_phone($phone), 0, 50),
            ':email' => $email !== '' ? mb_substr(mb_strtolower(trim($email)), 0, 190) : null,
            ':coaching_type' => mb_substr(trim($coaching_type), 0, 100),
            ':appointment_date' => $date,
            ':appointment_time' => $time,
            ':status' => in_array($status, ['pending', 'confirmed', 'cancelled'], true) ? $status : 'pending',
            ':notes' => $notes ? mb_substr($notes, 0, 2000) : null,
        ]);
        return (int) db()->lastInsertId();
    } finally {
        db_unlock_slot($date, $time);
    }
}

function db_update_appointment(int $id, string $name, string $phone, string $email, string $coaching_type, string $appointment_date, string $appointment_time, string $status, ?string $notes, ?int $orgId = null): bool
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_appointments_table();
    [$date, $time] = db_normalize_slot($appointment_date, $appointment_time);

    if ($name === '' || $phone === '') {
        throw new AppointmentValidationException('Ad ve telefon zorunludur.');
    }
    if (!db_valid_phone($phone)) {
        throw new AppointmentValidationException('Lütfen geçerli bir telefon numarası yazın.');
    }
    if ($email !== '' && !db_valid_email($email)) {
        throw new AppointmentValidationException('Lütfen geçerli bir e-posta adresi yazın.');
    }

    if (!db_lock_slot($date, $time)) {
        throw new AppointmentConflictException('Bu saat için işlem sürüyor. Lütfen birkaç saniye sonra tekrar deneyin.');
    }

    try {
        $conflict = db_find_slot_conflict($date, $time, $id, $orgId);
        if ($conflict) {
            throw new AppointmentConflictException($conflict['message']);
        }

        $stmt = db()->prepare(
            "UPDATE appointments
             SET name = :name, phone = :phone, email = :email, coaching_type = :coaching_type,
                 appointment_date = :appointment_date, appointment_time = :appointment_time, status = :status, notes = :notes
             WHERE id = :id AND organization_id = :org"
        );
        $stmt->execute([
            ':name' => mb_substr(trim($name), 0, 255),
            ':phone' => mb_substr(db_normalize_phone($phone), 0, 50),
            ':email' => $email !== '' ? mb_substr(mb_strtolower(trim($email)), 0, 190) : null,
            ':coaching_type' => mb_substr(trim($coaching_type), 0, 100),
            ':appointment_date' => $date,
            ':appointment_time' => $time,
            ':status' => in_array($status, ['pending', 'confirmed', 'cancelled'], true) ? $status : 'pending',
            ':notes' => $notes ? mb_substr($notes, 0, 2000) : null,
            ':id' => $id,
            ':org' => $orgId,
        ]);
        return true;
    } finally {
        db_unlock_slot($date, $time);
    }
}

function db_delete_appointment(int $id, ?int $orgId = null): bool
{
    $orgId = $orgId ?? current_org_id();
    db_ensure_appointments_table();
    $stmt = db()->prepare('DELETE FROM appointments WHERE id = :id AND organization_id = :org');
    $stmt->execute([':id' => $id, ':org' => $orgId]);
    return $stmt->rowCount() > 0;
}
