<?php

declare(strict_types=1);

namespace App\Models;

final class Product
{
    public function __construct(
        public readonly int $id,
        public readonly int $categoryId,
        public readonly string $name,
        public readonly string $description,
        public readonly string $price,
        public readonly int $stock,
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
            categoryId: (int) $row['category_id'],
            name: (string) $row['name'],
            description: (string) $row['description'],
            price: (string) $row['price'],
            stock: (int) $row['stock'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
