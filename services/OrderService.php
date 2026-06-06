<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;

final class OrderService
{
    private const MAX_SHIPPING_NAME_LENGTH = 100;
    private const MAX_SHIPPING_ADDRESS_LENGTH = 255;
    private const MAX_SHIPPING_CITY_LENGTH = 100;
    private const MAX_SHIPPING_POSTAL_CODE_LENGTH = 20;
    private const MAX_SHIPPING_PHONE_LENGTH = 30;
    private const ERROR_VALIDATION = 'Validation failed';
    private const ERROR_EMPTY_CART = 'Cart is empty';
    private const ERROR_NOT_FOUND = 'Order not found';
    private const ERROR_FORBIDDEN = 'Forbidden';

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly CartRepository $cartRepository,
        private readonly Validator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function checkout(int $userId, array $input): array
    {
        $errors = $this->validator->validate($input, $this->shippingRules());

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $cartItems = $this->cartRepository->findByUserId($userId);

        if ($cartItems === []) {
            return ['ok' => false, 'status' => 400, 'error' => self::ERROR_EMPTY_CART];
        }

        $shipping = $this->normalizeShipping($input);
        $result = $this->orderRepository->checkout($userId, $shipping, $cartItems);

        if (! $result['ok']) {
            return ['ok' => false, 'status' => $result['status'], 'error' => $result['error']];
        }

        return [
            'ok' => true,
            'data' => $this->formatOrderDetail($result['order'], $result['items']),
        ];
    }

    /**
     * @return array{ok: true, data: list<array<string, mixed>>}
     */
    public function listByUser(int $userId): array
    {
        $orders = $this->orderRepository->findByUserId($userId);

        return [
            'ok' => true,
            'data' => array_map($this->formatOrderSummary(...), $orders),
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string}
     */
    public function getByIdForUser(int $userId, int $orderId): array
    {
        $order = $this->orderRepository->findById($orderId);

        if ($order === null) {
            return $this->notFoundFailure();
        }

        if ($order->userId !== $userId) {
            return ['ok' => false, 'status' => 403, 'error' => self::ERROR_FORBIDDEN];
        }

        $items = $this->orderRepository->findItemsByOrderId($orderId);

        return ['ok' => true, 'data' => $this->formatOrderDetail($order, $items)];
    }

    /**
     * @return array<string, list<string>>
     */
    private function shippingRules(): array
    {
        return [
            'shipping_name' => ['required', 'maxLength:' . self::MAX_SHIPPING_NAME_LENGTH],
            'shipping_address' => ['required', 'maxLength:' . self::MAX_SHIPPING_ADDRESS_LENGTH],
            'shipping_city' => ['required', 'maxLength:' . self::MAX_SHIPPING_CITY_LENGTH],
            'shipping_postal_code' => ['required', 'maxLength:' . self::MAX_SHIPPING_POSTAL_CODE_LENGTH],
            'shipping_phone' => ['required', 'maxLength:' . self::MAX_SHIPPING_PHONE_LENGTH],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{shipping_name: string, shipping_address: string, shipping_city: string, shipping_postal_code: string, shipping_phone: string}
     */
    private function normalizeShipping(array $input): array
    {
        return [
            'shipping_name' => trim((string) $input['shipping_name']),
            'shipping_address' => trim((string) $input['shipping_address']),
            'shipping_city' => trim((string) $input['shipping_city']),
            'shipping_postal_code' => trim((string) $input['shipping_postal_code']),
            'shipping_phone' => trim((string) $input['shipping_phone']),
        ];
    }

    /**
     * @param list<OrderItem> $items
     * @return array<string, mixed>
     */
    private function formatOrderDetail(Order $order, array $items): array
    {
        return [
            ...$this->formatOrderSummary($order),
            'items' => array_map($this->formatOrderItem(...), $items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderSummary(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'total' => $order->total,
            'shipping_name' => $order->shippingName,
            'shipping_address' => $order->shippingAddress,
            'shipping_city' => $order->shippingCity,
            'shipping_postal_code' => $order->shippingPostalCode,
            'shipping_phone' => $order->shippingPhone,
            'created_at' => $order->createdAt,
            'updated_at' => $order->updatedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderItem(OrderItem $item): array
    {
        $lineTotal = number_format((float) $item->unitPrice * $item->quantity, 2, '.', '');

        return [
            'id' => $item->id,
            'product_id' => $item->productId,
            'product_name' => $item->productName,
            'unit_price' => $item->unitPrice,
            'quantity' => $item->quantity,
            'line_total' => $lineTotal,
        ];
    }

    /**
     * @param array<string, string> $errors
     * @return array{ok: false, status: int, error: string, details: array<string, string>}
     */
    private function validationFailure(array $errors): array
    {
        return [
            'ok' => false,
            'status' => 400,
            'error' => self::ERROR_VALIDATION,
            'details' => $errors,
        ];
    }

    /**
     * @return array{ok: false, status: int, error: string}
     */
    private function notFoundFailure(): array
    {
        return ['ok' => false, 'status' => 404, 'error' => self::ERROR_NOT_FOUND];
    }
}
