<?php
// app/Models/Payment.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Ödemeler (payments). Kart verisi tutulmaz. Yazma açık alan listeleriyle yapılır. */
class Payment extends BaseModel
{
    private const PROVIDERS = ['paytr', 'bank_transfer', 'cod'];
    private const STATUSES = ['initiated', 'pending', 'success', 'failed', 'refunded'];
    private const SUCCESS_EXTRA = ['payment_type', 'installment_count', 'note', 'callback_received_at'];

    protected string $table = 'payments';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'created_at', 'amount'];

    public function createPayment(array $data): int
    {
        foreach (['order_id', 'provider', 'amount'] as $req) {
            if (!isset($data[$req]) || $data[$req] === '') {
                throw new \InvalidArgumentException('Eksik ödeme alanı: ' . $req);
            }
        }
        $provider = (string) $data['provider'];
        $status = (string) ($data['status'] ?? 'initiated');
        if (!in_array($provider, self::PROVIDERS, true) || !in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Geçersiz ödeme sağlayıcısı veya durumu.');
        }
        if (is_float($data['amount']) || preg_match('/^\d{1,10}(\.\d{1,2})?$/', (string) $data['amount']) !== 1) {
            throw new \InvalidArgumentException('Geçersiz ödeme tutarı.');
        }
        $oid = $data['merchant_oid'] ?? null;
        if ($oid !== null && preg_match('/^[A-Za-z0-9]{1,64}$/', (string) $oid) !== 1) {
            throw new \InvalidArgumentException('merchant_oid yalnızca harf ve rakam içerebilir.');
        }
        return Database::instance()->insert(
            'INSERT INTO payments (order_id, provider, merchant_oid, amount, currency, status, payment_type, installment_count, note)
             VALUES (:o, :p, :m, :a, :c, :s, :pt, :ic, :n)',
            [
                ':o' => (int) $data['order_id'],
                ':p' => $provider,
                ':m' => $oid !== null ? (string) $oid : null,
                ':a' => (string) $data['amount'],
                ':c' => 'TRY',
                ':s' => $status,
                ':pt' => isset($data['payment_type']) ? mb_substr((string) $data['payment_type'], 0, 20) : null,
                ':ic' => isset($data['installment_count']) ? max(0, min(255, (int) $data['installment_count'])) : null,
                ':n' => isset($data['note']) ? mb_substr((string) $data['note'], 0, 255) : null,
            ]
        );
    }

    /** $forUpdate=true yalnızca transaction içinde (PayTR bildirimi idempotentliği için satır kilidi). */
    public function findByMerchantOid(string $merchantOid, bool $forUpdate = false): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM payments WHERE merchant_oid = :m LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''),
            [':m' => $merchantOid]
        );
    }

    public function latestForOrder(int $orderId): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM payments WHERE order_id = :o ORDER BY id DESC LIMIT 1',
            [':o' => $orderId]
        );
    }

    public function forOrder(int $orderId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM payments WHERE order_id = :o ORDER BY id ASC',
            [':o' => $orderId]
        );
    }

    /** extra beyaz liste: payment_type, installment_count, note, callback_received_at. */
    public function markSuccess(int $id, array $extra): void
    {
        $sets = ["status = 'success'"];
        $p = [':id' => $id];
        foreach ($extra as $col => $val) {
            if (!in_array($col, self::SUCCESS_EXTRA, true)) {
                throw new \InvalidArgumentException('İzin verilmeyen ödeme alanı: ' . $col);
            }
            $sets[] = $col . ' = :x_' . $col;
            $p[':x_' . $col] = $val;
        }
        if (!array_key_exists('callback_received_at', $extra)) {
            $sets[] = 'callback_received_at = NOW()';
        }
        // İadesi yapılmış ödeme tekrar başarıya çekilemez.
        Database::instance()->execute(
            "UPDATE payments SET " . implode(', ', $sets) . " WHERE id = :id AND status <> 'refunded'",
            $p
        );
    }

    /** Başarılı bir ödeme yanlışlıkla failed'a çevrilemesin diye yalnızca initiated/pending güncellenir. */
    public function markFailed(int $id, ?string $code, ?string $msg): void
    {
        Database::instance()->execute(
            "UPDATE payments SET status = 'failed', failed_reason_code = :c, failed_reason_msg = :m,
                    callback_received_at = NOW()
              WHERE id = :id AND status IN ('initiated', 'pending')",
            [
                ':c' => $code !== null ? mb_substr($code, 0, 20) : null,
                ':m' => $msg !== null ? mb_substr($msg, 0, 255) : null,
                ':id' => $id,
            ]
        );
    }

    /** Havale/EFT admin onayı; yalnızca bekleyen ödeme onaylanır (çift onay etkisiz). */
    public function confirmManual(int $id, int $adminId, ?string $note): void
    {
        Database::instance()->execute(
            "UPDATE payments SET status = 'success', confirmed_by = :a, confirmed_at = NOW(), note = :n
              WHERE id = :id AND status IN ('initiated', 'pending')",
            [':a' => $adminId, ':n' => $note !== null ? mb_substr($note, 0, 255) : null, ':id' => $id]
        );
    }

    /** Onay bekleyen havale/EFT ödemeleri (sayfalı). */
    public function pendingBankTransfers(int $page, int $perPage): array
    {
        $w = " WHERE p.provider = 'bank_transfer' AND p.status IN ('initiated', 'pending') AND o.status = 'pending_payment'";
        $from = ' FROM payments p INNER JOIN orders o ON o.id = p.order_id INNER JOIN users u ON u.id = o.user_id';
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $db = Database::instance();
        $total = (int) $db->fetchValue('SELECT COUNT(*)' . $from . $w);
        $items = $db->fetchAll(
            'SELECT p.*, o.order_number, o.grand_total, o.expires_at AS order_expires_at,
                    o.created_at AS order_created_at, u.first_name, u.last_name, u.email'
            . $from . $w . ' ORDER BY p.id ASC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}