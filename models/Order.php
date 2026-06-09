<?php

declare(strict_types=1);

namespace App\Models;

final class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_STATUS_AWAITING = 'awaiting';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';

    /** @var list<string> */
    public const VALID_PAYMENT_STATUSES = [
        self::PAYMENT_STATUS_AWAITING,
        self::PAYMENT_STATUS_PAID,
        self::PAYMENT_STATUS_FAILED,
    ];

    /** @var list<string> */
    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

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
        public readonly ?string $paywayTranId,
        public readonly ?string $paywayApv,
        public readonly string $paymentStatus,
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
            paywayTranId: isset($row['payway_tran_id']) ? (string) $row['payway_tran_id'] : null,
            paywayApv: isset($row['payway_apv']) ? (string) $row['payway_apv'] : null,
            paymentStatus: (string) ($row['payment_status'] ?? self::PAYMENT_STATUS_AWAITING),
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
