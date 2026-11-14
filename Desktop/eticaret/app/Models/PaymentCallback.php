<?php
// app/Models/PaymentCallback.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** PayTR bildirim günlüğü (payment_callbacks). EKLEMELİ. Payload'a kart verisi ASLA yazılmaz. */
class PaymentCallback extends BaseModel
{
    protected string $table = 'payment_callbacks';
    protected string $primaryKey = 'id';
    protected array $fillable = ['merchant_oid', 'payload', 'hash_valid', 'ip_address', 'result'];
    protected bool $softDeletes = false;
    protected bool $appendOnly = true;
    protected array $sortable = ['id', 'created_at'];

    public function log(?string $merchantOid, string $payloadJson, bool $hashValid, string $ipBin, ?string $result): int
    {
        // Günlük yazımı hiçbir koşulda patlamamalı: geçersiz IP baytı sıfır adresle değiştirilir.
        if (!in_array(strlen($ipBin), [4, 16], true)) {
            $ipBin = "\0\0\0\0";
        }
        return Database::instance()->insert(
            'INSERT INTO payment_callbacks (merchant_oid, payload, hash_valid, ip_address, result)
             VALUES (:m, :p, :h, :ip, :r)',
            [
                ':m' => $merchantOid !== null ? mb_substr($merchantOid, 0, 64) : null,
                ':p' => $payloadJson,
                ':h' => $hashValid ? 1 : 0,
                ':ip' => $ipBin,
                ':r' => $result !== null ? mb_substr($result, 0, 50) : null,
            ]
        );
    }

    public function recent(int $limit): array
    {
        $limit = max(1, min(500, $limit));
        return Database::instance()->fetchAll(
            'SELECT * FROM payment_callbacks ORDER BY id DESC LIMIT ' . $limit
        );
    }
}