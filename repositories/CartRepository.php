<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CartItem;
use PDO;
use RuntimeException;

final class CartRepository
{
    private const SELECT_WITH_PRODUCT = 'ci.id, ci.user_id, ci.product_id, ci.quantity, ci.created_at, ci.updated_at,
        p.name AS product_name, p.price AS product_price, p.stock AS product_stock';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<CartItem>
     */
    public function findByUserId(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SELECT_WITH_PRODUCT . '
             FROM cart_items ci
             INNER JOIN products p ON p.id = ci.product_id
             WHERE ci.user_id = :user_id
             ORDER BY ci.created_at ASC',
        );
        $statement->execute(['user_id' => $userId]);

        return array_map(
            static fn (array $row): CartItem => CartItem::fromRow($row),
            $statement->fetchAll(),
        );
    }

    public function upsertItem(int $userId, int $productId, int $quantity): CartItem
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity)
             VALUES (:user_id, :product_id, :quantity)
             ON DUPLICATE KEY UPDATE quantity = :updated_quantity',
        );
        $statement->execute([
            'user_id' => $userId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'updated_quantity' => $quantity,
        ]);

        $cartItem = $this->findItemByUserAndProduct($userId, $productId);

        if ($cartItem === null) {
            throw new RuntimeException('Failed to load cart item after upsert.');
        }

        return $cartItem;
    }

    public function removeItem(int $userId, int $productId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM cart_items WHERE user_id = :user_id AND product_id = :product_id',
        );
        $statement->execute(['user_id' => $userId, 'product_id' => $productId]);

        return $statement->rowCount() > 0;
    }

    public function clearByUserId(int $userId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM cart_items WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
    }

    private function findItemByUserAndProduct(int $userId, int $productId): ?CartItem
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SELECT_WITH_PRODUCT . '
             FROM cart_items ci
             INNER JOIN products p ON p.id = ci.product_id
             WHERE ci.user_id = :user_id AND ci.product_id = :product_id
             LIMIT 1',
        );
        $statement->execute(['user_id' => $userId, 'product_id' => $productId]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return CartItem::fromRow($row);
    }
}
