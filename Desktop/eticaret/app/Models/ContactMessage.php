<?php
// app/Models/ContactMessage.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** İletişim mesajları (contact_messages). */
class ContactMessage extends BaseModel
{
    private const STATUSES = ['new', 'read', 'replied'];

    protected string $table = 'contact_messages';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email', 'phone', 'subject', 'message', 'ip_address'];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'created_at', 'status'];

    /** data: name, email, subject, message, ip_address (ham bayt); phone isteğe bağlı. Durum her zaman 'new'. */
    public function createMessage(array $data): int
    {
        foreach (['name', 'email', 'subject', 'message', 'ip_address'] as $req) {
            if (!isset($data[$req]) || $data[$req] === '') {
                throw new \InvalidArgumentException('Eksik mesaj alanı: ' . $req);
            }
        }
        $ip = $data['ip_address'];
        if (!is_string($ip) || !in_array(strlen($ip), [4, 16], true)) {
            throw new \InvalidArgumentException('ip_address ham IP baytı olmalı.');
        }
        return Database::instance()->insert(
            "INSERT INTO contact_messages (name, email, phone, subject, message, ip_address, status)
             VALUES (:n, :e, :p, :s, :m, :ip, 'new')",
            [
                ':n' => mb_substr((string) $data['name'], 0, 120),
                ':e' => mb_substr((string) $data['email'], 0, 190),
                ':p' => isset($data['phone']) && $data['phone'] !== '' ? mb_substr((string) $data['phone'], 0, 20) : null,
                ':s' => mb_substr((string) $data['subject'], 0, 190),
                ':m' => mb_substr((string) $data['message'], 0, 10000),
                ':ip' => $ip,
            ]
        );
    }

    public function adminList(?string $status, int $page, int $perPage): array
    {
        $w = '';
        $p = [];
        if ($status !== null && $status !== '') {
            if (!in_array($status, self::STATUSES, true)) {
                $w = ' WHERE 1 = 0';
            } else {
                $w = ' WHERE status = :s';
                $p[':s'] = $status;
            }
        }
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $db = Database::instance();
        $total = (int) $db->fetchValue('SELECT COUNT(*) FROM contact_messages' . $w, $p);
        $items = $db->fetchAll(
            'SELECT * FROM contact_messages' . $w . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $p
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function markStatus(int $id, string $status, ?int $userId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Geçersiz mesaj durumu.');
        }
        Database::instance()->execute(
            'UPDATE contact_messages SET status = :s, handled_by = :h WHERE id = :id',
            [':s' => $status, ':h' => $status === 'new' ? null : $userId, ':id' => $id]
        );
    }

    public function countNew(): int
    {
        return (int) Database::instance()->fetchValue("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
    }
}