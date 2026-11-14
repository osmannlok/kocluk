<?php
// app/Models/Cart.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Sepet başlığı (carts). Fiyat saklanmaz; kalemler CartItem'dadır. */
class Cart extends BaseModel
{
    protected string $table = 'carts';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'guest_token', 'expires_at'];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'created_at', 'expires_at'];

    public function findByUserId(int $userId): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM carts WHERE user_id = :u LIMIT 1',
            [':u' => $userId]
        );
    }

    public function findByGuestHash(string $hash): ?array
    {
        $this->assertGuestHash($hash);
        return Database::instance()->fetchOne(
            'SELECT * FROM carts WHERE guest_token = :h LIMIT 1',
            [':h' => $hash]
        );
    }

    public function createForUser(int $userId): int
    {
        return Database::instance()->insert(
            'INSERT INTO carts (user_id, guest_token, expires_at) VALUES (:u, NULL, :e)',
            [':u' => $userId, ':e' => date('Y-m-d H:i:s', time() + 30 * 86400)]
        );
    }

    public function createForGuest(string $hash, string $expiresAt): int
    {
        // guest_token: imzalı çerezin SHA-256 özeti; ham çerez asla saklanmaz.
        $this->assertGuestHash($hash);
        return Database::instance()->insert(
            'INSERT INTO carts (user_id, guest_token, expires_at) VALUES (NULL, :h, :e)',
            [':h' => $hash, ':e' => $expiresAt]
        );
    }

    public function touch(int $cartId, string $expiresAt): void
    {
        Database::instance()->execute(
            'UPDATE carts SET expires_at = :e WHERE id = :id',
            [':e' => $expiresAt, ':id' => $cartId]
        );
    }

    /** Misafir sepetini kullanıcıya bağlar (kullanıcının mevcut sepeti varsa servis önce birleştirip siler: UNIQUE user_id). */
    public function assignToUser(int $cartId, int $userId): void
    {
        Database::instance()->execute(
            'UPDATE carts SET user_id = :u, guest_token = NULL WHERE id = :id',
            [':u' => $userId, ':id' => $cartId]
        );
    }

    public function deleteById(int $cartId): void
    {
        Database::instance()->execute('DELETE FROM carts WHERE id = :id', [':id' => $cartId]);
    }

    public function purgeExpired(): int
    {
        return Database::instance()->execute('DELETE FROM carts WHERE expires_at < NOW()');
    }

    private function assertGuestHash(string $hash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $hash) !== 1) {
            throw new \InvalidArgumentException('Geçersiz misafir sepet özeti.');
        }
    }
}