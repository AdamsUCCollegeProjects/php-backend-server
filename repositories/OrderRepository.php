<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use PDO;
use PDOException;
use RuntimeException;

final class OrderRepository
{
    private const ORDER_COLUMNS = 'id, user_id, status, total, shipping_name, shipping_address,
        shipping_city, shipping_postal_code, shipping_phone, created_at, updated_at';
    private const ITEM_COLUMNS = 'id, order_id, product_id, product_name, unit_price, quantity';
    private const ERROR_INSUFFICIENT_STOCK = 'Insufficient stock for one or more products';
    private const ERROR_CHECKOUT_FAILED = 'Checkout failed';

    public function __construct(
        private readonly PDO $pdo,
        private readonly ProductRepository $productRepository,
        private readonly CartRepository $cartRepository,
    ) {
    }

    /**
     * @return list<Order>
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query(
            'SELECT ' . self::ORDER_COLUMNS . ' FROM orders ORDER BY created_at DESC',
        );

        if ($statement === false) {
            return [];
        }

        return array_map(
            static fn (array $row): Order => Order::fromRow($row),
            $statement->fetchAll(),
        );
    }

    /**
     * @return list<Order>
     */
    public function findByUserId(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::ORDER_COLUMNS . ' FROM orders WHERE user_id = :user_id ORDER BY created_at DESC',
        );
        $statement->execute(['user_id' => $userId]);

        return array_map(
            static fn (array $row): Order => Order::fromRow($row),
            $statement->fetchAll(),
        );
    }

    public function findById(int $id): ?Order
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::ORDER_COLUMNS . ' FROM orders WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return Order::fromRow($row);
    }

    /**
     * @return list<OrderItem>
     */
    public function findItemsByOrderId(int $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::ITEM_COLUMNS . ' FROM order_items WHERE order_id = :order_id ORDER BY id ASC',
        );
        $statement->execute(['order_id' => $orderId]);

        return array_map(
            static fn (array $row): OrderItem => OrderItem::fromRow($row),
            $statement->fetchAll(),
        );
    }

    public function updateStatus(int $id, string $status): ?Order
    {
        $order = $this->findById($id);

        if ($order === null) {
            return null;
        }

        $statement = $this->pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => $id]);

        return $this->findById($id);
    }

    /**
     * @param list<CartItem> $cartItems
     * @param array{shipping_name: string, shipping_address: string, shipping_city: string, shipping_postal_code: string, shipping_phone: string} $shipping
     * @return array{ok: true, order: Order, items: list<OrderItem>}|array{ok: false, error: string, status: int}
     */
    public function checkout(int $userId, array $shipping, array $cartItems): array
    {
        try {
            $this->pdo->beginTransaction();

            $lockedProducts = $this->lockProductsForCheckout($cartItems);

            if (! $lockedProducts['ok']) {
                $this->pdo->rollBack();

                return $lockedProducts;
            }

            $total = $this->calculateTotal($cartItems);
            $order = $this->insertOrder($userId, $total, $shipping);
            $items = $this->insertOrderItems($order->id, $cartItems);
            $this->decrementStockForItems($cartItems);
            $this->cartRepository->clearByUserId($userId);

            $this->pdo->commit();

            return ['ok' => true, 'order' => $order, 'items' => $items];
        } catch (PDOException | RuntimeException) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['ok' => false, 'error' => self::ERROR_CHECKOUT_FAILED, 'status' => 500];
        }
    }

    /**
     * @param list<CartItem> $cartItems
     * @return array{ok: true}|array{ok: false, error: string, status: int}
     */
    private function lockProductsForCheckout(array $cartItems): array
    {
        foreach ($cartItems as $cartItem) {
            $product = $this->productRepository->findByIdForUpdate($cartItem->productId);

            if ($product === null || $product->stock < $cartItem->quantity) {
                return [
                    'ok' => false,
                    'error' => self::ERROR_INSUFFICIENT_STOCK,
                    'status' => 400,
                ];
            }
        }

        return ['ok' => true];
    }

    /**
     * @param list<CartItem> $cartItems
     */
    private function calculateTotal(array $cartItems): string
    {
        $total = 0.0;

        foreach ($cartItems as $cartItem) {
            $total += (float) ($cartItem->productPrice ?? '0') * $cartItem->quantity;
        }

        return number_format($total, 2, '.', '');
    }

    /**
     * @param array{shipping_name: string, shipping_address: string, shipping_city: string, shipping_postal_code: string, shipping_phone: string} $shipping
     */
    private function insertOrder(int $userId, string $total, array $shipping): Order
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO orders (
                user_id, total, shipping_name, shipping_address, shipping_city, shipping_postal_code, shipping_phone
             ) VALUES (
                :user_id, :total, :shipping_name, :shipping_address, :shipping_city, :shipping_postal_code, :shipping_phone
             )',
        );
        $statement->execute([
            'user_id' => $userId,
            'total' => $total,
            'shipping_name' => $shipping['shipping_name'],
            'shipping_address' => $shipping['shipping_address'],
            'shipping_city' => $shipping['shipping_city'],
            'shipping_postal_code' => $shipping['shipping_postal_code'],
            'shipping_phone' => $shipping['shipping_phone'],
        ]);

        $order = $this->findById((int) $this->pdo->lastInsertId());

        if ($order === null) {
            throw new RuntimeException('Failed to load order after insert.');
        }

        return $order;
    }

    /**
     * @param list<CartItem> $cartItems
     * @return list<OrderItem>
     */
    private function insertOrderItems(int $orderId, array $cartItems): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
             VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity)',
        );

        foreach ($cartItems as $cartItem) {
            $statement->execute([
                'order_id' => $orderId,
                'product_id' => $cartItem->productId,
                'product_name' => $cartItem->productName ?? 'Unknown product',
                'unit_price' => $cartItem->productPrice ?? '0.00',
                'quantity' => $cartItem->quantity,
            ]);
        }

        return $this->findItemsByOrderId($orderId);
    }

    /**
     * @param list<CartItem> $cartItems
     */
    private function decrementStockForItems(array $cartItems): void
    {
        foreach ($cartItems as $cartItem) {
            $updated = $this->productRepository->decrementStock(
                $cartItem->productId,
                $cartItem->quantity,
            );

            if (! $updated) {
                throw new RuntimeException('Failed to decrement product stock.');
            }
        }
    }
}
