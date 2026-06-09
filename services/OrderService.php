<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

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
    private const ERROR_INVALID_STATUS = 'Invalid order status';
    private const ERROR_USER_NOT_FOUND = 'User not found';
    private const ERROR_PAYMENT_INIT_FAILED = 'Payment initiation failed';
    private const ERROR_MISSING_TRAN_ID = 'Missing tran_id';
    private const ERROR_PAYMENT_UPDATE_FAILED = 'Failed to update order payment';
    private const PAYWAY_TRAN_ID_TIME_FORMAT = 'YmdHis';
    private const PAYWAY_SUCCESS_STATUS = 0;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly CartRepository $cartRepository,
        private readonly UserRepository $userRepository,
        private readonly PayWayServiceInterface $payWayService,
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

        return $this->completeCheckoutWithPayment($userId, $result['order'], $result['items']);
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, error: string}
     */
    public function processPayWayWebhook(string $tranId, string $apv, mixed $status): array
    {
        if ($tranId === '') {
            return ['ok' => false, 'status' => 400, 'error' => self::ERROR_MISSING_TRAN_ID];
        }

        $order = $this->orderRepository->findByPayWayTranId($tranId);

        if ($order === null) {
            return $this->notFoundFailure();
        }

        if ($order->paymentStatus === Order::PAYMENT_STATUS_PAID) {
            return ['ok' => true];
        }

        if ((int) $status !== self::PAYWAY_SUCCESS_STATUS) {
            return ['ok' => true];
        }

        $updatedOrder = $this->orderRepository->markPaymentPaid($order->id, $apv);

        if ($updatedOrder === null) {
            return ['ok' => false, 'status' => 500, 'error' => self::ERROR_PAYMENT_UPDATE_FAILED];
        }

        return ['ok' => true];
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
     * @return array{ok: true, data: list<array<string, mixed>>}
     */
    public function listAll(): array
    {
        $orders = $this->orderRepository->findAll();

        return [
            'ok' => true,
            'data' => $this->formatAdminOrderSummaries($orders),
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string}
     */
    public function getById(int $orderId): array
    {
        $order = $this->orderRepository->findById($orderId);

        if ($order === null) {
            return $this->notFoundFailure();
        }

        $items = $this->orderRepository->findItemsByOrderId($orderId);

        return ['ok' => true, 'data' => $this->formatAdminOrderDetail($order, $items)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function updateStatus(int $orderId, array $input): array
    {
        $errors = $this->validator->validate($input, ['status' => ['required']]);

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $status = trim((string) $input['status']);

        if (! in_array($status, Order::VALID_STATUSES, true)) {
            return ['ok' => false, 'status' => 400, 'error' => self::ERROR_INVALID_STATUS];
        }

        $updatedOrder = $this->orderRepository->updateStatus($orderId, $status);

        if ($updatedOrder === null) {
            return $this->notFoundFailure();
        }

        $items = $this->orderRepository->findItemsByOrderId($orderId);

        return ['ok' => true, 'data' => $this->formatAdminOrderDetail($updatedOrder, $items)];
    }

    /**
     * @param list<Order> $orders
     * @return list<array<string, mixed>>
     */
    public function formatAdminOrderSummaries(array $orders): array
    {
        return array_map($this->formatAdminOrderSummary(...), $orders);
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
     * @param list<OrderItem> $items
     * @return array<string, mixed>
     */
    private function formatAdminOrderDetail(Order $order, array $items): array
    {
        return [
            ...$this->formatAdminOrderSummary($order),
            'items' => array_map($this->formatOrderItem(...), $items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAdminOrderSummary(Order $order): array
    {
        return [
            ...$this->formatOrderSummary($order),
            'user_id' => $order->userId,
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
            'payment_status' => $order->paymentStatus,
            'payway_tran_id' => $order->paywayTranId,
            'payway_apv' => $order->paywayApv,
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
     * @param list<OrderItem> $items
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string}
     */
    private function completeCheckoutWithPayment(int $userId, Order $order, array $items): array
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            return ['ok' => false, 'status' => 500, 'error' => self::ERROR_USER_NOT_FOUND];
        }

        $orderWithTranId = $this->persistPayWayTranId($order);

        if ($orderWithTranId === null) {
            return ['ok' => false, 'status' => 500, 'error' => self::ERROR_PAYMENT_INIT_FAILED];
        }

        try {
            $purchaseResult = $this->payWayService->createPurchase($orderWithTranId, $user);
        } catch (RuntimeException) {
            return ['ok' => false, 'status' => 502, 'error' => self::ERROR_PAYMENT_INIT_FAILED];
        }

        return [
            'ok' => true,
            'data' => [
                ...$this->formatOrderDetail($orderWithTranId, $items),
                'checkout_html' => $purchaseResult->checkoutHtml,
            ],
        ];
    }

    private function persistPayWayTranId(Order $order): ?Order
    {
        $tranId = $this->generatePayWayTranId($order->id);

        return $this->orderRepository->updatePayWayTranId($order->id, $tranId);
    }

    private function generatePayWayTranId(int $orderId): string
    {
        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format(self::PAYWAY_TRAN_ID_TIME_FORMAT);

        return sprintf('ORD-%d-%s', $orderId, $timestamp);
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
