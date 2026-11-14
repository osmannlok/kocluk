<?php
// app/Models/LoginAttempt.php

declare(strict_types=1);

namespace App\Models;

final class LoginAttempt extends BaseModel
{
    protected string $table = 'login_attempts';
    protected bool $softDeletes = false;
    protected bool $appendOnly = true;
    protected array $fillable = ['email', 'ip_address', 'user_agent', 'success', 'attempted_at'];

    public function record(string $email, string $ipBin, ?string $ua, bool $success): void
    {
        $hex = $this->ipHex($ipBin);

        $this->db->execute(
            'INSERT INTO `login_attempts` (`email`, `ip_address`, `user_agent`, `success`) '
            . 'VALUES (:email, UNHEX(:ip), :ua, :success)',
            [
                ':email' => $this->normalizeEmail($email),
                ':ip' => $hex,
                ':ua' => $this->truncate($ua, 255),
                ':success' => $success ? 1 : 0,
            ]
        );
    }

    public function failedCountByEmail(string $email, int $minutes): int
    {
        $minutes = $this->clamp($minutes, 1, 10080);

        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `login_attempts` WHERE `email` = :email AND `success` = 0 '
            . 'AND `attempted_at` >= (NOW() - INTERVAL ' . $minutes . ' MINUTE)',
            [':email' => $this->normalizeEmail($email)]
        );
    }

    public function failedCountByIp(string $ipBin, int $minutes): int
    {
        $hex = $this->ipHex($ipBin);
        $minutes = $this->clamp($minutes, 1, 10080);

        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `login_attempts` WHERE `ip_address` = UNHEX(:ip) AND `success` = 0 '
            . 'AND `attempted_at` >= (NOW() - INTERVAL ' . $minutes . ' MINUTE)',
            [':ip' => $hex]
        );
    }

    /** Saklama süresi temizliği (cron); tek izinli silme yoludur. */
    public function purgeOlderThan(int $days): int
    {
        $days = $this->clamp($days, 1, 3650);

        return $this->db->execute(
            'DELETE FROM `login_attempts` WHERE `attempted_at` < (NOW() - INTERVAL ' . $days . ' DAY)'
        );
    }

    private function normalizeEmail(string $email): string
    {
        return (string) $this->truncate(mb_strtolower(trim($email)), 190);
    }
}