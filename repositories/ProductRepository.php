<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use PDO;
use RuntimeException;

final class ProductRepository
{
    private const SELECT_COLUMNS = 'id, category_id, name, description, price, stock, created_at, updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<Product>
     */
    public function findAll(?int $categoryId = null): array
    {
        if ($categoryId !== null) {
            return $this->findAllByCategoryId($categoryId);
        }

        $statement = $this->pdo->query(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM products ORDER BY name ASC',
        );

        if ($statement === false) {
            return [];
        }

        return $this->mapRows($statement->fetchAll());
    }

    public function findById(int $id): ?Product
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM products WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return Product::fromRow($row);
    }

    public function findByIdForUpdate(int $id): ?Product
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM products WHERE id = :id LIMIT 1 FOR UPDATE',
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return Product::fromRow($row);
    }

    public function countByCategoryId(int $categoryId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM products WHERE category_id = :category_id',
        );
        $statement->execute(['category_id' => $categoryId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{category_id: int, name: string, description: string, price: string, stock: int} $data
     */
    public function create(array $data): Product
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (category_id, name, description, price, stock)
             VALUES (:category_id, :name, :description, :price, :stock)',
        );
        $statement->execute($data);

        $product = $this->findById((int) $this->pdo->lastInsertId());

        if ($product === null) {
            throw new RuntimeException('Failed to load product after insert.');
        }

        return $product;
    }

    /**
     * @param array{category_id: int, name: string, description: string, price: string, stock: int} $data
     */
    public function update(int $id, array $data): ?Product
    {
        $statement = $this->pdo->prepare(
            'UPDATE products
             SET category_id = :category_id,
                 name = :name,
                 description = :description,
                 price = :price,
                 stock = :stock
             WHERE id = :id',
        );
        $statement->execute(['id' => $id] + $data);

        return $this->findById($id);
    }

    public function decrementStock(int $id, int $quantity): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE products SET stock = stock - :quantity WHERE id = :id AND stock >= :minimum_stock',
        );
        $statement->execute([
            'id' => $id,
            'quantity' => $quantity,
            'minimum_stock' => $quantity,
        ]);

        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return list<Product>
     */
    private function findAllByCategoryId(int $categoryId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM products WHERE category_id = :category_id ORDER BY name ASC',
        );
        $statement->execute(['category_id' => $categoryId]);

        return $this->mapRows($statement->fetchAll());
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<Product>
     */
    private function mapRows(array $rows): array
    {
        return array_map(static fn (array $row): Product => Product::fromRow($row), $rows);
    }
}
