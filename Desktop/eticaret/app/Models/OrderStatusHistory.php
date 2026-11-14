<?php
// app/Models/OrderStatusHistory.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Sipariş durum geçmişi (order_status_history). EKLEMELİ: yalnızca INSERT. */
class OrderStatusHistory extends BaseModel
{
    protected string $table = 'order_status_history';
    protected string $primaryKey = 'id';
    protected array $fillable = ['order_id', 'from_status', 'to_status', 'changed_by', 'note'];
    protected bool $softDeletes = false;
    protected bool $appendOnly = true;
    protected array $sortable = ['id', 'created_at'];

    public function add(int $orderId, ?string $from, string $to, ?int $userId, ?string $note): void
    {
        Database::instance()->execute(
            'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note)
             VALUES (:o, :f, :t, :u, :n)',
            [
                ':o' => $orderId,
                ':f' => $from !== null ? mb_substr($from, 0, 30) : null,
                ':t' => mb_substr($to, 0, 30),
                ':u' => $userId,
                ':n' => $note !== null ? mb_substr($note, 0, 255) : null,
            ]
        );
    }

    public function forOrder(int $orderId): array
    {
        return Database::instance()->fetchAll(
            "SELECT h.*, CONCAT(u.first_name, ' ', u.last_name) AS changed_by_name
               FROM order_status_history h
               LEFT JOIN users u ON u.id = h.changed_by
              WHERE h.order_id = :o ORDER BY h.id ASC",
            [':o' => $orderId]
        );
    }
}