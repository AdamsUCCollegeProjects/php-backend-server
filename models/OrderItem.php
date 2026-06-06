<?php

declare(strict_types=1);

namespace App\Models;

final class OrderItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $orderId,
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $unitPrice,
        public readonly int $quantity,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            orderId: (int) $row['order_id'],
            productId: (int) $row['product_id'],
            productName: (string) $row['product_name'],
            unitPrice: (string) $row['unit_price'],
            quantity: (int) $row['quantity'],
        );
    }
}
