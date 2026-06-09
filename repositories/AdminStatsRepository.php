<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Order;
use PDO;

final class AdminStatsRepository
{
    private const ORDER_COLUMNS = 'id, user_id, status, total, shipping_name, shipping_address,
        shipping_city, shipping_postal_code, shipping_phone, payway_tran_id, payway_apv,
        payment_status, created_at, updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countOrders(): int
    {
        $statement = $this->pdo->query('SELECT COUNT(*) FROM orders');

        return (int) $statement->fetchColumn();
    }

    public function sumRevenue(): string
    {
        $statement = $this->pdo->query(
            "SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != 'cancelled'",
        );

        return number_format((float) $statement->fetchColumn(), 2, '.', '');
    }

    public function countUsers(): int
    {
        $statement = $this->pdo->query('SELECT COUNT(*) FROM users');

        return (int) $statement->fetchColumn();
    }

    public function countProducts(): int
    {
        $statement = $this->pdo->query('SELECT COUNT(*) FROM products');

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<Order>
     */
    public function findRecentOrders(int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::ORDER_COLUMNS . ' FROM orders ORDER BY created_at DESC LIMIT :limit',
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): Order => Order::fromRow($row),
            $statement->fetchAll(),
        );
    }
}
