<?php
// app/Models/CouponUsage.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Kupon kullanım kayıtları (coupon_usages). order_id UNIQUE: bir siparişte tek kupon. */
class CouponUsage extends BaseModel
{
    protected string $table = 'coupon_usages';
    protected string $primaryKey = 'id';
    protected array $fillable = ['coupon_id', 'user_id', 'order_id', 'discount_amount'];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'created_at'];

    public function countByUser(int $couponId, int $userId): int
    {
        return (int) Database::instance()->fetchValue(
            'SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = :c AND user_id = :u',
            [':c' => $couponId, ':u' => $userId]
        );
    }

    public function record(int $couponId, int $userId, int $orderId, string $amount): int
    {
        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount) !== 1) {
            throw new \InvalidArgumentException('Geçersiz indirim tutarı.');
        }
        return Database::instance()->insert(
            'INSERT INTO coupon_usages (coupon_id, user_id, order_id, discount_amount) VALUES (:c, :u, :o, :a)',
            [':c' => $couponId, ':u' => $userId, ':o' => $orderId, ':a' => $amount]
        );
    }

    /** İptal/başarısız ödemede kullanım hakkını geri vermek için. */
    public function deleteByOrder(int $orderId): void
    {
        Database::instance()->execute('DELETE FROM coupon_usages WHERE order_id = :o', [':o' => $orderId]);
    }
}