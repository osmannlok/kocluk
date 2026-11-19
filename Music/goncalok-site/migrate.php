<?php
// ============================================================
// GONCA LÖK — Veritabanı migration (CLI)
// Kullanım: php migrate.php [--owner-email=MAIL] [--owner-name=İSİM] [--password=ŞİFRE] [--dry-run]
// Idempotent: birden çok kez çalıştırılabilir.
// ÖNEMLİ: Önce mysqldump ile yedek alın.
// ============================================================
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.' . PHP_EOL);
}
chdir(__DIR__);
require __DIR__ . '/config.php';

$options = getopt('', ['owner-email::', 'owner-name::', 'password::', 'dry-run']);
$dryRun = isset($options['dry-run']);
$say = static function (string $msg) use (&$dryRun): void { print(($dryRun ? '[DRY-RUN] ' : '') . $msg . PHP_EOL); };
$say($dryRun ? 'Dry-run modu; değişiklik yapılmayacak.' : 'Migration başlıyor…');

$pdo = db();

$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column"
    );
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};
$constraintExists = static function (string $table, string $name) use ($pdo): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.table_constraints
         WHERE table_schema = DATABASE() AND table_name = :table AND constraint_name = :name"
    );
    $stmt->execute([':table' => $table, ':name' => $name]);
    return (int) $stmt->fetchColumn() > 0;
};
$run = static function (string $sql) use ($pdo, $dryRun, $say): void {
    if ($dryRun) {
        $say('SQL: ' . preg_replace('/\s+/', ' ', trim($sql)));
        return;
    }
    $pdo->exec($sql);
};

// ---- 1) Temel organizasyon tabloları ----
$run(
    "CREATE TABLE IF NOT EXISTS organizations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$run(
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        organization_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(191) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('owner','coach','admin') NOT NULL DEFAULT 'coach',
        active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_users_org (organization_id),
        CONSTRAINT fk_users_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$run(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(80) PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$run(
    "CREATE TABLE IF NOT EXISTS rate_limits (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        bucket VARCHAR(64) NOT NULL,
        identifier VARCHAR(190) NOT NULL,
        hits INT NOT NULL DEFAULT 1,
        window_start DATETIME NOT NULL,
        KEY idx_rate (bucket, identifier, window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$run(
    "INSERT IGNORE INTO organizations (id, name, slug) VALUES
        (1, 'Gonca LÖK', 'gonca-lok')"
);

// ---- 2) Tüm ilgili tablolara organization_id ekle + doldur ----
$tables = [
    'blog_posts', 'appointments', 'admin_busy_slots', 'students', 'sessions', 'goals',
    'goal_metrics', 'actions', 'consents', 'audit_logs', 'subjects', 'lessons', 'exams',
    'career_profiles', 'applications', 'risk_flags', 'interventions', 'portal_tokens',
    'notification_log', 'session_templates',
];
foreach ($tables as $table) {
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $table . "'"
    )->fetchColumn() > 0;
    if (!$tableExists) {
        continue; // tablo henüz yoksa: taze kurulum, şema migration 1'i bu tabloyu zaten kuracak
    }
    if (!$columnExists($table, 'organization_id')) {
        $run("ALTER TABLE `{$table}` ADD COLUMN organization_id INT NULL AFTER id");
        $run("UPDATE `{$table}` SET organization_id = 1 WHERE organization_id IS NULL");
        $run("ALTER TABLE `{$table}` MODIFY organization_id INT NOT NULL DEFAULT 1");
        $run("ALTER TABLE `{$table}` ADD KEY idx_{$table}_org (organization_id)");
        $say("{$table}: organization_id eklendi (mevcut kayıtlar organizasyon #1'e bağlandı).");
    } else {
        $run("UPDATE `{$table}` SET organization_id = 1 WHERE organization_id IS NULL OR organization_id = 0");
        $say("{$table}: organization_id zaten var, alan dolduruldu.");
    }
    $fk = "fk_{$table}_org";
    if (!$constraintExists($table, $fk)) {
        $run("ALTER TABLE `{$table}` ADD CONSTRAINT {$fk} FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE RESTRICT");
    }
}

// ---- 3) Sahip (owner) kullanıcısı — yoksa oluştur ----
if ($dryRun) {
    $say('Owner kullanıcısı oluşturulup oluşturulmayacağını kontrol etmek için gerçek çalıştırma gerekir (users tablosu henüz yok olabilir).');
} else {
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount === 0) {
    $ownerEmail = mb_strtolower(trim((string) ($options['owner-email'] ?? env('ADMIN_EMAIL', 'goncalokofficial@gmail.com'))));
    $ownerName = trim((string) ($options['owner-name'] ?? 'Gonca LÖK'));
    $password = (string) ($options['password'] ?? bin2hex(random_bytes(9))); // 18 hex karakter
    if (strlen($password) < 8) {
        fwrite(STDERR, "HATA: --password en az 8 karakter olmalı.\n");
        exit(1);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($dryRun) {
        $say("Owner kullanıcısı oluşturulacak: {$ownerEmail} (şifre random/belirtilen)");
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO users (organization_id, name, email, password_hash, role, active) VALUES (1, :name, :email, :hash, :role, 1)'
        );
        $stmt->execute([':name' => $ownerName, ':email' => $ownerEmail, ':hash' => $hash, ':role' => 'owner']);
        $say("Owner kullanıcısı oluşturuldu: {$ownerEmail}");
        $say("Giriş şifresi: {$password}");
        $say('Bu şifreyi şimdi kaydedin; sonradan: php bin/reset-admin-password.php --email=' . $ownerEmail);
    }
} else {
    $say('Users tablosu zaten dolu; kullanıcı oluşturulmadı.');
}
}

// ---- 4) Migration kaydı ----
if (!$dryRun) {
    $pdo->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (:v)')->execute([':v' => '2026_11_18_org_bootstrap']);
}
$say('Migration tamamlandı.');
