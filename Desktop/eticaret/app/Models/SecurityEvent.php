<?php
// app/Models/SecurityEvent.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;

/**
 * Güvenlik olayları (security_events). EKLEMELİ: yalnızca INSERT.
 * record() STATIC'tir: 05b'deki SecurityEvent::record(...) çağrıları ve (new SecurityEvent())->record(...) birlikte çalışır.
 */
final class SecurityEvent extends BaseModel
{
    private const SEVERITIES = ['info', 'warning', 'critical'];

    protected string $table = 'security_events';
    protected string $primaryKey = 'id';
    protected array $fillable = ['type', 'severity', 'user_id', 'ip_address', 'detail'];
    protected bool $softDeletes = false;
    protected bool $appendOnly = true;
    protected array $sortable = ['id', 'created_at', 'severity', 'type'];

    /** $ip: metin IP ("1.2.3.4") veya ham bayt (4/16). Günlük yazımı isteği asla düşürmez; hata yutulur. */
    public static function record(string $type, string $severity, ?int $userId, ?string $ip, array $detail = []): void
    {
        try {
            if (!in_array($severity, self::SEVERITIES, true)) {
                $severity = 'info';
            }
            $bin = "\0\0\0\0";
            if ($ip !== null && $ip !== '') {
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    $conv = inet_pton($ip);
                    if ($conv !== false) {
                        $bin = $conv;
                    }
                } elseif (in_array(strlen($ip), [4, 16], true)) {
                    $bin = $ip;
                }
            }
            $json = json_encode(
                self::maskDetail($detail),
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
            Database::instance()->execute(
                'INSERT INTO security_events (type, severity, user_id, ip_address, detail) VALUES (:t, :s, :u, :ip, :d)',
                [
                    ':t' => mb_substr($type, 0, 60),
                    ':s' => $severity,
                    ':u' => $userId,
                    ':ip' => $bin,
                    ':d' => $json === false ? null : $json,
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('security_events yazılamadı', ['type' => $type, 'err' => $e->getMessage()]);
        }
    }

    /** filters: type, severity, user_id, date_from, date_to. */
    public function search(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $p = [];
        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'e.type = :t';
            $p[':t'] = $type;
        }
        $sev = (string) ($filters['severity'] ?? '');
        if ($sev !== '') {
            if (in_array($sev, self::SEVERITIES, true)) {
                $where[] = 'e.severity = :s';
                $p[':s'] = $sev;
            } else {
                $where[] = '1 = 0';
            }
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'e.user_id = :u';
            $p[':u'] = (int) $filters['user_id'];
        }
        $from = (string) ($filters['date_from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $where[] = 'e.created_at >= :df';
            $p[':df'] = $from . ' 00:00:00';
        }
        $to = (string) ($filters['date_to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $where[] = 'e.created_at < DATE_ADD(:dt, INTERVAL 1 DAY)';
            $p[':dt'] = $to . ' 00:00:00';
        }
        $w = ' WHERE ' . implode(' AND ', $where);
        $fromSql = ' FROM security_events e LEFT JOIN users u ON u.id = e.user_id';
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $db = Database::instance();
        $total = (int) $db->fetchValue('SELECT COUNT(*)' . $fromSql . $w, $p);
        $items = $db->fetchAll(
            'SELECT e.*, u.email AS user_email' . $fromSql . $w
            . ' ORDER BY e.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $p
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** Son N dakikadaki belirli türden olay sayısı (alarm/eşik kontrolleri için). */
    public function countSince(string $type, int $minutes): int
    {
        $minutes = max(1, min(525600, $minutes));
        return (int) Database::instance()->fetchValue(
            'SELECT COUNT(*) FROM security_events WHERE type = :t AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $minutes . ' MINUTE)',
            [':t' => $type]
        );
    }

    private static function maskDetail(array $v): array
    {
        $out = [];
        foreach ($v as $k => $x) {
            if (is_string($k) && preg_match('/pass(word|wd)?|token|secret|api_key|merchant_(key|salt)|tc_identity|card_number|cvv/i', $k) === 1) {
                $out[$k] = '***';
            } else {
                $out[$k] = is_array($x) ? self::maskDetail($x) : $x;
            }
        }
        return $out;
    }
}