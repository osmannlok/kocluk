<?php
// app/Models/Shipment.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Kargo kayıtları (shipments). Manuel: firma + takip numarası. */
class Shipment extends BaseModel
{
    private const UPDATABLE = ['carrier', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at', 'note'];

    protected string $table = 'shipments';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'shipped_at', 'created_at'];

    public function forOrder(int $orderId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM shipments WHERE order_id = :o ORDER BY id ASC',
            [':o' => $orderId]
        );
    }

    public function createShipment(array $data): int
    {
        foreach (['order_id', 'carrier', 'tracking_number'] as $req) {
            if (!isset($data[$req]) || trim((string) $data[$req]) === '') {
                throw new \InvalidArgumentException('Eksik kargo alanı: ' . $req);
            }
        }
        return Database::instance()->insert(
            'INSERT INTO shipments (order_id, carrier, tracking_number, tracking_url, shipped_at, note, created_by)
             VALUES (:o, :c, :t, :u, :s, :n, :cb)',
            [
                ':o' => (int) $data['order_id'],
                ':c' => mb_substr(trim((string) $data['carrier']), 0, 60),
                ':t' => mb_substr(trim((string) $data['tracking_number']), 0, 80),
                ':u' => $this->safeTrackingUrl($data['tracking_url'] ?? null),
                ':s' => (string) ($data['shipped_at'] ?? date('Y-m-d H:i:s')),
                ':n' => isset($data['note']) ? mb_substr((string) $data['note'], 0, 255) : null,
                ':cb' => isset($data['created_by']) ? (int) $data['created_by'] : null,
            ]
        );
    }

    /** Etkilenen satır varsa true (değer değişmediyse false dönebilir). */
    public function updateShipment(int $id, array $data): bool
    {
        $sets = [];
        $p = [':id' => $id];
        foreach ($data as $col => $val) {
            if (!in_array($col, self::UPDATABLE, true)) {
                throw new \InvalidArgumentException('İzin verilmeyen kargo alanı: ' . $col);
            }
            if ($col === 'tracking_url') {
                $val = $this->safeTrackingUrl($val);
            } elseif ($col === 'carrier') {
                $val = mb_substr(trim((string) $val), 0, 60);
            } elseif ($col === 'tracking_number') {
                $val = mb_substr(trim((string) $val), 0, 80);
            } elseif ($col === 'note' && $val !== null) {
                $val = mb_substr((string) $val, 0, 255);
            }
            $sets[] = $col . ' = :f_' . $col;
            $p[':f_' . $col] = $val;
        }
        if ($sets === []) {
            return false;
        }
        return Database::instance()->execute('UPDATE shipments SET ' . implode(', ', $sets) . ' WHERE id = :id', $p) > 0;
    }

    public function markDelivered(int $id): void
    {
        Database::instance()->execute(
            'UPDATE shipments SET delivered_at = COALESCE(delivered_at, NOW()) WHERE id = :id',
            [':id' => $id]
        );
    }

    /** Takip bağlantısı müşteriye gösterildiği için yalnızca http(s) kabul edilir (javascript: şeması engeli). */
    private function safeTrackingUrl(mixed $url): ?string
    {
        if ($url === null || trim((string) $url) === '') {
            return null;
        }
        $u = trim((string) $url);
        if (preg_match('#^https?://#i', $u) !== 1 || filter_var($u, FILTER_VALIDATE_URL) === false || strlen($u) > 255) {
            throw new \InvalidArgumentException('Geçersiz takip bağlantısı.');
        }
        return $u;
    }
}