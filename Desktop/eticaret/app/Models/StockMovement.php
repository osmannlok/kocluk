<?php
// app/Models/StockMovement.php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final class StockMovement extends BaseModel
{
    private const TYPES = ['initial', 'purchase', 'sale', 'cancel_return', 'manual_adjust', 'correction'];

    protected string $table = 'stock_movements';
    protected bool $softDeletes = false;
    protected bool $appendOnly = true;

    protected array $fillable = [
        'variant_id',
        'user_id',
        'type',
        'quantity_change',
        'quantity_after',
        'reference_type',
        'reference_id',
        'note',
    ];

    protected array $extraColumns = ['created_at'];

    public function record(
        int $variantId,
        ?int $userId,
        string $type,
        int $change,
        int $after,
        ?string $refType,
        ?int $refId,
        ?string $note
    ): int {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Geçersiz stok hareketi türü.');
        }

        return $this->create([
            'variant_id' => $variantId,
            'user_id' => $userId,
            'type' => $type,
            'quantity_change' => $change,
            'quantity_after' => $after,
            'reference_type' => $this->truncate($refType, 50),
            'reference_id' => $refId,
            'note' => $this->truncate($note, 255),
        ]);
    }

    /**
     * Ek anahtar: user_name (işlemi yapan kullanıcı; yoksa null).
     *
     * @return array{items: list<array>, total: int, page: int, per_page: int, pages: int}
     */
    public function forVariant(int $variantId, int $page, int $perPage): array
    {
        return $this->paginateRaw(
            'sm.*, ' . $this->userNameSql(),
            '`stock_movements` sm LEFT JOIN `users` u ON u.`id` = sm.`user_id`',
            'sm.`variant_id` = :variant_id',
            [':variant_id' => $variantId],
            'sm.`id` DESC',
            $page,
            $perPage
        );
    }

    /**
     * Ek anahtarlar: user_name, variant_name, sku, product_name.
     *
     * @return list<array>
     */
    public function recent(int $limit): array
    {
        $limit = $this->clamp($limit, 1, 200);

        return $this->db->fetchAll(
            'SELECT sm.*, ' . $this->userNameSql() . ', v.`name` AS variant_name, v.`sku` AS sku, p.`name` AS product_name '
            . 'FROM `stock_movements` sm '
            . 'INNER JOIN `product_variants` v ON v.`id` = sm.`variant_id` '
            . 'INNER JOIN `products` p ON p.`id` = v.`product_id` '
            . 'LEFT JOIN `users` u ON u.`id` = sm.`user_id` '
            . 'ORDER BY sm.`id` DESC LIMIT ' . $limit
        );
    }

    private function userNameSql(): string
    {
        return "NULLIF(TRIM(CONCAT(COALESCE(u.`first_name`, ''), ' ', COALESCE(u.`last_name`, ''))), '') AS user_name";
    }
}