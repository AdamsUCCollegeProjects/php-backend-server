<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\CartItem;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;

final class CartService
{
    private const ERROR_VALIDATION = 'Validation failed';
    private const ERROR_PRODUCT_NOT_FOUND = 'Product not found';
    private const ERROR_INSUFFICIENT_STOCK = 'Insufficient stock';
    private const ERROR_CART_ITEM_NOT_FOUND = 'Cart item not found';

    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly ProductRepository $productRepository,
        private readonly Validator $validator,
    ) {
    }

    /**
     * @return array{ok: true, data: array{items: list<array<string, mixed>>, total: string}}
     */
    public function getCart(int $userId): array
    {
        $items = $this->cartRepository->findByUserId($userId);

        return [
            'ok' => true,
            'data' => [
                'items' => array_map($this->formatCartItem(...), $items),
                'total' => $this->calculateCartTotal($items),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function upsertItem(int $userId, array $input): array
    {
        $errors = $this->validator->validate($input, $this->itemRules());
        $errors = array_merge($errors, $this->validateItemFields($input));

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $productId = (int) $input['product_id'];
        $quantity = (int) $input['quantity'];
        $stockError = $this->validateProductStock($productId, $quantity);

        if ($stockError !== null) {
            return $stockError;
        }

        $cartItem = $this->cartRepository->upsertItem($userId, $productId, $quantity);

        return ['ok' => true, 'data' => $this->formatCartItem($cartItem)];
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, error: string}
     */
    public function removeItem(int $userId, int $productId): array
    {
        if ($this->productRepository->findById($productId) === null) {
            return ['ok' => false, 'status' => 404, 'error' => self::ERROR_PRODUCT_NOT_FOUND];
        }

        if (! $this->cartRepository->removeItem($userId, $productId)) {
            return ['ok' => false, 'status' => 404, 'error' => self::ERROR_CART_ITEM_NOT_FOUND];
        }

        return ['ok' => true];
    }

    /**
     * @return array<string, list<string>>
     */
    private function itemRules(): array
    {
        return [
            'product_id' => ['required'],
            'quantity' => ['required'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validateItemFields(array $input): array
    {
        $errors = [];

        if (isset($input['product_id']) && filter_var($input['product_id'], FILTER_VALIDATE_INT) === false) {
            $errors['product_id'] = 'product_id must be an integer';
        }

        if (isset($input['quantity']) && filter_var($input['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors['quantity'] = 'quantity must be a positive integer';
        }

        return $errors;
    }

    /**
     * @return array{ok: false, status: int, error: string}|null
     */
    private function validateProductStock(int $productId, int $quantity): ?array
    {
        $product = $this->productRepository->findById($productId);

        if ($product === null) {
            return ['ok' => false, 'status' => 404, 'error' => self::ERROR_PRODUCT_NOT_FOUND];
        }

        if ($product->stock < $quantity) {
            return ['ok' => false, 'status' => 400, 'error' => self::ERROR_INSUFFICIENT_STOCK];
        }

        return null;
    }

    /**
     * @param list<CartItem> $items
     */
    private function calculateCartTotal(array $items): string
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += (float) ($item->productPrice ?? '0') * $item->quantity;
        }

        return number_format($total, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCartItem(CartItem $item): array
    {
        $unitPrice = $item->productPrice ?? '0.00';
        $lineTotal = number_format((float) $unitPrice * $item->quantity, 2, '.', '');

        return [
            'id' => $item->id,
            'product_id' => $item->productId,
            'product_name' => $item->productName,
            'unit_price' => $unitPrice,
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
}
