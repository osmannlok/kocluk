<?php
// app/Models/AuditLog.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Yönetici işlem günlüğü (audit_logs). EKLEMELİ: UPDATE/DELETE yok. Hassas alanlar maskelenir. */
class AuditLog extends BaseModel
{
    protected string $table = 'audit_logs';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'ip_address', 'user_agent'];
    protected bool $softDeletes = false;
    protected bool $appendOnly = true;
    protected array $sortable = ['id', 'created_at', 'action'];

    public function write(
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?array $old,
        ?array $new,
        ?string $ipBin,
        ?string $ua
    ): void {
        if ($ipBin !== null && !in_array(strlen($ipBin), [4, 16], true)) {
            $ipBin = null;
        }
        Database::instance()->execute(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (:u, :a, :et, :ei, :o, :n, :ip, :ua)',
            [
                ':u' => $userId,
                ':a' => mb_substr($action, 0, 100),
                ':et' => $entityType !== null ? mb_substr($entityType, 0, 60) : null,
                ':ei' => $entityId,
                ':o' => $this->encode($old),
                ':n' => $this->encode($new),
                ':ip' => $ipBin,
                ':ua' => $ua !== null ? mb_substr($ua, 0, 255) : null,
            ]
        );
    }

    /** filters: user_id, action (öneki), entity_type, entity_id, date_from, date_to. */
    public function search(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $p = [];
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :u';
            $p[':u'] = (int) $filters['user_id'];
        }
        $act = trim((string) ($filters['action'] ?? ''));
        if ($act !== '') {
            $where[] = 'a.action LIKE :act';
            $p[':act'] = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $act) . '%';
        }
        $et = trim((string) ($filters['entity_type'] ?? ''));
        if ($et !== '') {
            $where[] = 'a.entity_type = :et';
            $p[':et'] = $et;
        }
        if (!empty($filters['entity_id'])) {
            $where[] = 'a.entity_id = :ei';
            $p[':ei'] = (int) $filters['entity_id'];
        }
        $from = (string) ($filters['date_from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $where[] = 'a.created_at >= :df';
            $p[':df'] = $from . ' 00:00:00';
        }
        $to = (string) ($filters['date_to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $where[] = 'a.created_at < DATE_ADD(:dt, INTERVAL 1 DAY)';
            $p[':dt'] = $to . ' 00:00:00';
        }
        $w = ' WHERE ' . implode(' AND ', $where);
        $from = ' FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id';
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $db = Database::instance();
        $total = (int) $db->fetchValue('SELECT COUNT(*)' . $from . $w, $p);
        $items = $db->fetchAll(
            "SELECT a.*, u.email AS user_email, CONCAT(u.first_name, ' ', u.last_name) AS user_name" . $from . $w
            . ' ORDER BY a.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $p
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function encode(?array $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $json = json_encode($this->mask($v), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $json === false ? null : $json;
    }

    /** Parola/token/kart/TC kimlik gibi alanlar günlüğe asla düz yazılmaz. */
    private function mask(array $v): array
    {
        $out = [];
        foreach ($v as $k => $x) {
            if (is_string($k) && preg_match('/pass(word|wd)?|token|secret|api_key|merchant_(key|salt)|tc_identity|card_number|cvv/i', $k) === 1) {
                $out[$k] = '***';
            } else {
                $out[$k] = is_array($x) ? $this->mask($x) : $x;
            }
        }
        return $out;
    }
}