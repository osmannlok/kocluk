<?php
// ============================================================
// GONCA LÖK — Admin şifresi sıfırlama (CLI)
// Kullanım: php bin/reset-admin-password.php --email=kullanici@example.com [--password=yeniSifre]
// Şifre belirtilmezse rastgele üretilir ve bir kez ekrana yazılır.
// ============================================================
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu betik yalnızca komut satırından çalıştırılabilir.' . PHP_EOL);
}
chdir(dirname(__DIR__));
require __DIR__ . '/../config.php';

$options = getopt('', ['email:', 'password::']);
$email = mb_strtolower(trim((string) ($options['email'] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "HATA: --email geçerli bir adres olmalı.\n");
    exit(1);
}
$password = (string) ($options['password'] ?? bin2hex(random_bytes(9)));
if (strlen($password) < 12) {
    fwrite(STDERR, "HATA: Şifre en az 12 karakter olmalı.\n");
    exit(1);
}
$stmt = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$id = (int) $stmt->fetchColumn();
if ($id === 0) {
    fwrite(STDERR, "HATA: Bu e-posta ile kullanıcı bulunamadı: {$email}\n");
    exit(1);
}
db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
    ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);
echo "Şifre güncellendi: {$email}\n";
echo "Yeni şifre: {$password}\n";
