<?php

declare(strict_types=1);

namespace App\Models;

final class Order
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $status,
        public readonly string $total,
        public readonly string $shippingName,
        public readonly string $shippingAddress,
        public readonly string $shippingCity,
        public readonly string $shippingPostalCode,
        public readonly string $shippingPhone,
        public readonly string $createdAt,
        public readonly string $updatedAt,
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
            status: (string) $row['status'],
            total: (string) $row['total'],
            shippingName: (string) $row['shipping_name'],
            shippingAddress: (string) $row['shipping_address'],
            shippingCity: (string) $row['shipping_city'],
            shippingPostalCode: (string) $row['shipping_postal_code'],
            shippingPhone: (string) $row['shipping_phone'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
