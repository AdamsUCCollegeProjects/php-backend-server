<?php

declare(strict_types=1);

namespace App\Models;

final class CartItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $productId,
        public readonly int $quantity,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $productName = null,
        public readonly ?string $productPrice = null,
        public readonly ?int $productStock = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            productId: (int) $row['product_id'],
            quantity: (int) $row['quantity'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
            productName: isset($row['product_name']) ? (string) $row['product_name'] : null,
            productPrice: isset($row['product_price']) ? (string) $row['product_price'] : null,
            productStock: isset($row['product_stock']) ? (int) $row['product_stock'] : null,
        );
    }
}
