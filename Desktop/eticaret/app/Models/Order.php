<?php
// app/Models/Order.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Siparişler (orders). Yazma yalnızca açık alan listeleriyle (createOrder, updateStatusFields). */
class Order extends BaseModel
{
    private const STATUSES = ['pending_payment', 'paid', 'preparing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    private const METHODS = ['credit_card', 'bank_transfer', 'cod'];
    private const PAY_STATUSES = ['pending', 'paid', 'failed', 'refunded'];
    private const UPDATABLE = [
        'status', 'payment_status', 'paid_at', 'shipped_at', 'delivered_at',
        'cancelled_at', 'cancel_reason', 'expires_at',
    ];

    protected string $table = 'orders';
    protected string $primaryKey = 'id';
    // Mass assignment kapalı: sipariş yazımı createOrder/updateStatusFields açık listeleriyle yapılır.
    protected array $fillable = [];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'created_at', 'grand_total', 'status'];

    public function createOrder(array $data): int
    {
        foreach ([
            'user_id', 'payment_method', 'subtotal', 'grand_total', 'shipping_address',
            'billing_address', 'kvkk_consent_at', 'distance_sales_consent_at', 'consent_ip',
        ] as $req) {
            if (!array_key_exists($req, $data) || $data[$req] === null || $data[$req] === '') {
                throw new \InvalidArgumentException('Eksik sipariş alanı: ' . $req);
            }
        }
        $status = (string) ($data['status'] ?? 'pending_payment');
        $method = (string) $data['payment_method'];
        $payStatus = (string) ($data['payment_status'] ?? 'pending');
        if (!in_array($status, self::STATUSES, true) || !in_array($method, self::METHODS, true)
            || !in_array($payStatus, self::PAY_STATUSES, true)) {
            throw new \InvalidArgumentException('Geçersiz sipariş durumu veya ödeme yöntemi.');
        }
        $consentIp = $data['consent_ip'];
        if (!is_string($consentIp) || !in_array(strlen($consentIp), [4, 16], true)) {
            throw new \InvalidArgumentException('consent_ip ham IP baytı olmalı.');
        }
        $ip = $data['ip_address'] ?? null;
        if ($ip !== null && (!is_string($ip) || !in_array(strlen($ip), [4, 16], true))) {
            $ip = null;
        }
        $addr = static fn (mixed $v): string => is_array($v)
            ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : (string) $v;
        $ua = isset($data['consent_user_agent']) ? mb_substr((string) $data['consent_user_agent'], 0, 255) : null;
        $note = isset($data['customer_note']) && $data['customer_note'] !== ''
            ? mb_substr((string) $data['customer_note'], 0, 500) : null;

        $params = [
            ':user_id' => (int) $data['user_id'],
            ':status' => $status,
            ':payment_method' => $method,
            ':payment_status' => $payStatus,
            ':currency' => 'TRY',
            ':subtotal' => $this->money($data['subtotal']),
            ':discount_total' => $this->money($data['discount_total'] ?? '0.00'),
            ':coupon_id' => isset($data['coupon_id']) ? (int) $data['coupon_id'] : null,
            ':coupon_code' => isset($data['coupon_code']) ? mb_substr((string) $data['coupon_code'], 0, 40) : null,
            ':shipping_total' => $this->money($data['shipping_total'] ?? '0.00'),
            ':cod_fee' => $this->money($data['cod_fee'] ?? '0.00'),
            ':grand_total' => $this->money($data['grand_total']),
            ':tax_total' => $this->money($data['tax_total'] ?? '0.00'),
            ':total_weight_grams' => max(0, (int) ($data['total_weight_grams'] ?? 0)),
            ':shipping_address' => $addr($data['shipping_address']),
            ':billing_address' => $addr($data['billing_address']),
            ':customer_note' => $note,
            ':kvkk_consent_at' => (string) $data['kvkk_consent_at'],
            ':distance_sales_consent_at' => (string) $data['distance_sales_consent_at'],
            ':consent_ip' => $consentIp,
            ':consent_user_agent' => $ua,
            ':ip_address' => $ip,
            ':expires_at' => $data['expires_at'] ?? null,
        ];
        return Database::instance()->insert(
            'INSERT INTO orders (user_id, status, payment_method, payment_status, currency, subtotal,
                discount_total, coupon_id, coupon_code, shipping_total, cod_fee, grand_total, tax_total,
                total_weight_grams, shipping_address, billing_address, customer_note, kvkk_consent_at,
                distance_sales_consent_at, consent_ip, consent_user_agent, ip_address, expires_at)
             VALUES (:user_id, :status, :payment_method, :payment_status, :currency, :subtotal,
                :discount_total, :coupon_id, :coupon_code, :shipping_total, :cod_fee, :grand_total, :tax_total,
                :total_weight_grams, :shipping_address, :billing_address, :customer_note, :kvkk_consent_at,
                :distance_sales_consent_at, :consent_ip, :consent_user_agent, :ip_address, :expires_at)',
            $params
        );
    }

    public function setOrderNumber(int $id, string $orderNumber): void
    {
        Database::instance()->execute(
            'UPDATE orders SET order_number = :n WHERE id = :id',
            [':n' => mb_substr($orderNumber, 0, 30), ':id' => $id]
        );
    }

    public function findByNumber(string $orderNumber): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM orders WHERE order_number = :n LIMIT 1',
            [':n' => $orderNumber]
        );
    }

    /** IDOR koruması: sipariş her zaman user_id ile sınırlanır. */
    public function findForUser(string $orderNumber, int $userId): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM orders WHERE order_number = :n AND user_id = :u LIMIT 1',
            [':n' => $orderNumber, ':u' => $userId]
        );
    }

    /** Transaction içinde çağrılmalı; satırı kilitler (durum geçişi/iptal yarışlarına karşı). */
    public function findByIdForUpdate(int $id): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM orders WHERE id = :id LIMIT 1 FOR UPDATE',
            [':id' => $id]
        );
    }

    public function listForUser(int $userId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $db = Database::instance();
        $total = (int) $db->fetchValue('SELECT COUNT(*) FROM orders WHERE user_id = :u', [':u' => $userId]);
        $items = $db->fetchAll(
            'SELECT o.*, (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
               FROM orders o WHERE o.user_id = :u
              ORDER BY o.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            [':u' => $userId]
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function adminList(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $p = [];
        $this->enumFilter($where, $p, 'o.status', ':st', $filters['status'] ?? null, self::STATUSES);
        $this->enumFilter($where, $p, 'o.payment_method', ':pm', $filters['payment_method'] ?? null, self::METHODS);
        $this->enumFilter($where, $p, 'o.payment_status', ':ps', $filters['payment_status'] ?? null, self::PAY_STATUSES);
        $from = (string) ($filters['date_from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $where[] = 'o.created_at >= :df';
            $p[':df'] = $from . ' 00:00:00';
        }
        $to = (string) ($filters['date_to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $where[] = 'o.created_at < DATE_ADD(:dt, INTERVAL 1 DAY)';
            $p[':dt'] = $to . ' 00:00:00';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = "(o.order_number LIKE :q1 OR u.email LIKE :q2 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :q3)";
            $p[':q1'] = $like;
            $p[':q2'] = $like;
            $p[':q3'] = $like;
        }
        $w = ' WHERE ' . implode(' AND ', $where);
        $from = ' FROM orders o INNER JOIN users u ON u.id = o.user_id';
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $db = Database::instance();
        $total = (int) $db->fetchValue('SELECT COUNT(*)' . $from . $w, $p);
        $items = $db->fetchAll(
            'SELECT o.*, u.first_name, u.last_name, u.email' . $from . $w
            . ' ORDER BY o.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $p
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** Yalnızca beyaz listedeki alanlar güncellenir; tutar/adres/kullanıcı alanları değiştirilemez. */
    public function updateStatusFields(int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $sets = [];
        $p = [':id' => $id];
        foreach ($fields as $col => $val) {
            if (!in_array($col, self::UPDATABLE, true)) {
                throw new \InvalidArgumentException('İzin verilmeyen sipariş alanı: ' . $col);
            }
            if ($col === 'status' && !in_array($val, self::STATUSES, true)) {
                throw new \InvalidArgumentException('Geçersiz sipariş durumu.');
            }
            if ($col === 'payment_status' && !in_array($val, self::PAY_STATUSES, true)) {
                throw new \InvalidArgumentException('Geçersiz ödeme durumu.');
            }
            if ($col === 'cancel_reason' && $val !== null) {
                $val = mb_substr((string) $val, 0, 255);
            }
            $sets[] = $col . ' = :f_' . $col;
            $p[':f_' . $col] = $val;
        }
        Database::instance()->execute('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = :id', $p);
    }

    public function expiredPending(string $now, int $limit): array
    {
        $limit = max(1, min(500, $limit));
        return Database::instance()->fetchAll(
            "SELECT * FROM orders
              WHERE status = 'pending_payment' AND expires_at IS NOT NULL AND expires_at <= :n
              ORDER BY expires_at ASC LIMIT " . $limit,
            [':n' => $now]
        );
    }

    /** durum => adet; tüm durumlar (0 dahil) döner. */
    public function countByStatus(): array
    {
        $out = array_fill_keys(self::STATUSES, 0);
        foreach (Database::instance()->fetchAll('SELECT status, COUNT(*) AS c FROM orders GROUP BY status') as $r) {
            $out[(string) $r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /** Satırlar: sale_date, order_count, total (ödenmiş/yoldaki/teslim siparişler). */
    public function salesByDay(int $days): array
    {
        $days = max(1, min(366, $days));
        return Database::instance()->fetchAll(
            "SELECT DATE(COALESCE(paid_at, created_at)) AS sale_date, COUNT(*) AS order_count,
                    COALESCE(SUM(grand_total), 0) AS total
               FROM orders
              WHERE status IN ('paid', 'preparing', 'shipped', 'delivered')
                AND COALESCE(paid_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL " . $days . " DAY)
              GROUP BY sale_date ORDER BY sale_date ASC"
        );
    }

    private function enumFilter(array &$where, array &$p, string $col, string $name, mixed $val, array $allowed): void
    {
        if ($val === null || $val === '') {
            return;
        }
        if (is_string($val) && in_array($val, $allowed, true)) {
            $where[] = $col . ' = ' . $name;
            $p[$name] = $val;
        } else {
            $where[] = '1 = 0';
        }
    }

    private function money(mixed $v): string
    {
        if (is_float($v) || !(is_string($v) || is_int($v)) || preg_match('/^\d{1,10}(\.\d{1,2})?$/', (string) $v) !== 1) {
            throw new \InvalidArgumentException('Geçersiz tutar biçimi.');
        }
        return (string) $v;
    }
}