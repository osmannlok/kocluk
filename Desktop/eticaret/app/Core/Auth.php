<?php
// app/Core/Auth.php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const SESSION_KEY = 'auth_user_id';

    private static bool $loaded = false;
    private static ?array $user = null;
    /** @var array<string,true> */
    private static array $permissions = [];

    public static function check(): bool
    {
        self::load();
        return self::$user !== null;
    }

    public static function user(): ?array
    {
        self::load();
        return self::$user;
    }

    public static function id(): ?int
    {
        self::load();
        return self::$user === null ? null : (int) self::$user['id'];
    }

    // Rol adına sabit kod yok: yetki yalnızca role_permissions tablosundan gelir.
    public static function can(string $perm): bool
    {
        self::load();
        return self::$user !== null && isset(self::$permissions[$perm]);
    }

    public static function hasRole(string $r): bool
    {
        self::load();
        return self::$user !== null && self::$user['role_name'] === $r;
    }

    public static function login(int $userId): void
    {
        // Yetki değişiminde oturum kimliği yenilenir (session fixation); CSRF token'ı da yenilenir.
        Session::regenerate();
        Session::forget('_csrf');
        Session::put(self::SESSION_KEY, $userId);
        self::reset();
    }

    public static function logout(): void
    {
        self::reset();
        Session::destroy();
        Session::start(); // sonraki flash mesajları için temiz oturum
    }

    private static function reset(): void
    {
        self::$loaded = false;
        self::$user = null;
        self::$permissions = [];
    }

    // Kullanıcı ve yetkiler istek başına bir kez DB'den tazelenir; askıya alınan/silinen kullanıcı anında düşer.
    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $raw = Session::get(self::SESSION_KEY);
        $id = is_int($raw) ? $raw : ((is_string($raw) && ctype_digit($raw)) ? (int) $raw : 0);
        if ($id <= 0) {
            return;
        }

        $db = Database::instance();
        $row = $db->fetchOne(
            'SELECT u.id, u.role_id, r.name AS role_name, u.first_name, u.last_name, u.email,
                    u.status, u.email_verified_at, u.deleted_at
               FROM users u
               INNER JOIN roles r ON r.id = u.role_id
              WHERE u.id = :id LIMIT 1',
            ['id' => $id]
        );

        if ($row === null || $row === false || $row['deleted_at'] !== null || $row['status'] !== 'active') {
            self::reset();
            self::$loaded = true;
            Session::destroy();
            Session::start();
            return;
        }

        unset($row['deleted_at']);
        self::$user = $row;

        $codes = $db->fetchAll(
            'SELECT p.code FROM role_permissions rp
               INNER JOIN permissions p ON p.id = rp.permission_id
              WHERE rp.role_id = :rid',
            ['rid' => (int) $row['role_id']]
        );
        foreach ($codes as $c) {
            self::$permissions[(string) $c['code']] = true;
        }
    }
}