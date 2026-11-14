<?php
// app/Services/AuditService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use Throwable;

/**
 * Denetim ve güvenlik olayı yazıcısı. Yazma hatası ana işlemi düşürmez.
 */
final class AuditService
{
    private const SEVERITIES = ['info', 'warning', 'critical'];

    /** Anahtar adında geçerse değer '***' olur (parola, token, hash, TC, kart vb.). */
    private const SENSITIVE_FRAGMENTS = [
        'password', 'passwd', 'pass', 'token', 'hash', 'secret', 'tc_identity',
        'tc_no', 'card', 'cvv', 'api_key', 'merchant_key', 'merchant_salt',
    ];

    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null
    ): void {
        try {
            $request = Request::capture();
            $ua = $request->userAgent();

            (new AuditLog())->write(
                self::currentUserId(),
                mb_substr($action, 0, 100),
                $entityType !== null ? mb_substr($entityType, 0, 60) : null,
                $entityId,
                self::mask($old),
                self::mask($new),
                ip_to_bin($request->ip()),
                $ua !== '' ? mb_substr($ua, 0, 255) : null
            );
        } catch (Throwable $e) {
            // Denetim yazılamasa da iş akışı sürer; yalnızca loga düşer.
            Logger::error('Denetim kaydı yazılamadı', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    public static function security(string $type, string $severity, array $detail = []): void
    {
        try {
            if (!in_array($severity, self::SEVERITIES, true)) {
                $severity = 'info';
            }

            SecurityEvent::record(
                mb_substr($type, 0, 60),
                $severity,
                self::currentUserId(),
                Request::capture()->ip(),
                self::mask($detail) ?? []
            );
        } catch (Throwable $e) {
            Logger::error('Güvenlik olayı yazılamadı', ['type' => $type, 'error' => $e->getMessage()]);
        }
    }

    private static function currentUserId(): ?int
    {
        try {
            return Auth::id();
        } catch (Throwable) {
            return null;
        }
    }

    /** Neden özyinelemeli maskeleme: iç içe dizilerde de hassas veri günlüğe girmesin. */
    private static function mask(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $out[$key] = '***';
                continue;
            }
            $out[$key] = is_array($value) ? self::mask($value) : $value;
        }

        return $out;
    }

    private static function isSensitive(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }
}