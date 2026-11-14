<?php
// app/Models/ProductImage.php

declare(strict_types=1);

namespace App\Models;

final class ProductImage extends BaseModel
{
    protected string $table = 'product_images';
    protected bool $softDeletes = false;
    protected array $fillable = ['product_id', 'variant_id', 'path', 'alt_text', 'sort_order', 'is_primary'];
    protected array $extraColumns = ['created_at'];

    /** @return list<array> */
    public function forProduct(int $productId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `product_images` WHERE `product_id` = :product_id '
            . 'ORDER BY `is_primary` DESC, `sort_order` ASC, `id` ASC',
            [':product_id' => $productId]
        );
    }

    /** Görsel bu ürüne ait değilse hiçbir şey değişmez. */
    public function setPrimary(int $productId, int $imageId): void
    {
        $this->db->transaction(function () use ($productId, $imageId): void {
            $row = $this->db->fetchOne(
                'SELECT `id` FROM `product_images` WHERE `id` = :id AND `product_id` = :product_id LIMIT 1 FOR UPDATE',
                [':id' => $imageId, ':product_id' => $productId]
            );
            if ($row === null) {
                return;
            }

            $this->db->execute(
                'UPDATE `product_images` SET `is_primary` = 0 WHERE `product_id` = :product_id AND `is_primary` = 1 AND `id` <> :id',
                [':product_id' => $productId, ':id' => $imageId]
            );
            $this->db->execute(
                'UPDATE `product_images` SET `is_primary` = 1 WHERE `id` = :id AND `product_id` = :product_id',
                [':id' => $imageId, ':product_id' => $productId]
            );
        });
    }

    /**
     * @param array<int|string, int|string> $idToOrder görsel id => sıra
     *
     * Not: sözleşme gereği ürün kimliği alınmaz; çağıran, kimliklerin aynı ürüne
     * ait olduğunu (IDOR) önceden doğrulamalıdır.
     */
    public function reorder(array $idToOrder): void
    {
        $pairs = [];
        foreach ($idToOrder as $imageId => $order) {
            if (
                (is_int($imageId) || (is_string($imageId) && ctype_digit($imageId)))
                && (is_int($order) || (is_string($order) && ctype_digit($order)))
                && (int) $imageId > 0
            ) {
                $pairs[(int) $imageId] = $this->clamp((int) $order, 0, 1000000);
            }
        }
        if ($pairs === []) {
            return;
        }

        $this->db->transaction(function () use ($pairs): void {
            foreach ($pairs as $imageId => $order) {
                $this->db->execute(
                    'UPDATE `product_images` SET `sort_order` = :sort_order WHERE `id` = :id',
                    [':sort_order' => $order, ':id' => $imageId]
                );
            }
        });
    }
}