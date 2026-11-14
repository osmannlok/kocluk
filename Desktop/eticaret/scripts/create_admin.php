<?php
// scripts/create_admin.php
declare(strict_types=1);

use App\Core\Config;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;

// Yalnızca CLI: web üzerinden çalıştırılamaz.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
require_once $base . '/app/Helpers/functions.php';
Config::load($base);

$fail = static function (string $message): never {
    fwrite(STDERR, "HATA: " . $message . "\n");
    exit(1);
};

$ask = static function (string $label): string {
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);

    return $line === false ? '' : trim($line);
};

// Neden stty -echo: parola ekranda ve terminal geçmişinde görünmesin.
$askSecret = static function (string $label): string {
    fwrite(STDOUT, $label);
    $tty = function_exists('stream_isatty') && stream_isatty(STDIN);
    if ($tty) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($tty) {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }

    return $line === false ? '' : rtrim($line, "\r\n");
};

$options = getopt('', ['first:', 'last:', 'email:', 'password:']);
$usedPasswordArg = isset($options['password']) && is_string($options['password']);

$roles = new Role();
$users = new User();

$adminRole = $roles->findByName('admin');
if ($adminRole === null) {
    $fail('admin rolü bulunamadı. Önce database/01_auth_rbac.sql çalıştırılmalı.');
}

// Zaten admin varsa ikinci admin yalnızca açık onayla açılır.
if ($users->count(['role_id' => (int) $adminRole['id']]) > 0) {
    fwrite(STDOUT, "UYARI: Sistemde zaten en az bir admin var.\n");
    $answer = mb_strtolower($ask("İkinci admin açmak için 'onaylıyorum' yazın: "));
    if (!in_array($answer, ['onaylıyorum', 'onayliyorum'], true)) {
        $fail('İşlem iptal edildi.');
    }
}

$firstName = isset($options['first']) && is_string($options['first']) ? trim($options['first']) : $ask('Ad: ');
$lastName = isset($options['last']) && is_string($options['last']) ? trim($options['last']) : $ask('Soyad: ');
$email = mb_strtolower(isset($options['email']) && is_string($options['email']) ? trim($options['email']) : $ask('E-posta: '));

if ($firstName === '' || mb_strlen($firstName) > 80 || $lastName === '' || mb_strlen($lastName) > 80) {
    $fail('Ad ve soyad zorunludur (en fazla 80 karakter).');
}
if (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $fail('Geçerli bir e-posta girin.');
}
if ($users->emailExists($email)) {
    $fail('Bu e-posta ile kayıtlı bir kullanıcı zaten var.');
}

if ($usedPasswordArg) {
    $password = (string) $options['password'];
} else {
    $password = $askSecret('Parola: ');
    $confirm = $askSecret('Parola (tekrar): ');
    if ($password !== $confirm) {
        $fail('Parolalar eşleşmiyor.');
    }
}

$policyErrors = (new AuthService())->validatePasswordPolicy($password, $email);
if ($policyErrors !== []) {
    $fail("Parola politikası sağlanmadı:\n - " . implode("\n - ", $policyErrors));
}

$userId = $users->create([
    'role_id' => (int) $adminRole['id'],
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
    'status' => 'active',
    'email_verified_at' => now(),
    'kvkk_consent_at' => now(),
    'marketing_consent' => 0,
]);

// CLI'de oturum/Request yok: denetim kaydı doğrudan modelle yazılır.
try {
    (new AuditLog())->write(null, 'user.create_admin_cli', 'user', $userId, null, ['email' => $email, 'role' => 'admin'], null, 'cli');
} catch (Throwable $e) {
    fwrite(STDERR, "Uyarı: denetim kaydı yazılamadı.\n");
}

fwrite(STDOUT, "Admin oluşturuldu (id: {$userId}, e-posta: {$email}).\n\n");
fwrite(STDOUT, "GÜVENLİK HATIRLATMASI:\n");
fwrite(STDOUT, " - Parolayı güvenli bir yöneticide saklayın; bu betiği üretimde işiniz bitince çalıştırılamaz hale getirin.\n");
if ($usedPasswordArg) {
    fwrite(STDOUT, " - Parolayı argümanla verdiniz: komut satırı geçmişini temizleyin (history -c) ve parolayı değiştirin.\n");
}
fwrite(STDOUT, " - Üretimde HTTPS ve APP_DEBUG=false olduğundan emin olun; ilk girişten sonra parolayı değiştirin.\n");