<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\Product;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;

final class ProductService
{
    private const MAX_NAME_LENGTH = 200;
    private const MAX_DESCRIPTION_LENGTH = 5000;
    private const ERROR_VALIDATION = 'Validation failed';
    private const ERROR_NOT_FOUND = 'Product not found';
    private const ERROR_CATEGORY_NOT_FOUND = 'Category not found';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly Validator $validator,
    ) {
    }

    /**
     * @return array{ok: true, data: list<array<string, mixed>>}
     */
    public function listAll(?int $categoryId = null): array
    {
        if ($categoryId !== null && $this->categoryRepository->findById($categoryId) === null) {
            return $this->categoryNotFoundFailure();
        }

        $products = $this->productRepository->findAll($categoryId);

        return [
            'ok' => true,
            'data' => array_map($this->formatProduct(...), $products),
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string}
     */
    public function getById(int $id): array
    {
        $product = $this->productRepository->findById($id);

        if ($product === null) {
            return $this->notFoundFailure();
        }

        return ['ok' => true, 'data' => $this->formatProduct($product)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function create(array $input): array
    {
        $validation = $this->validateProductInput($input);

        if (! $validation['ok']) {
            return $validation;
        }

        $product = $this->productRepository->create($validation['data']);

        return ['ok' => true, 'data' => $this->formatProduct($product)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function update(int $id, array $input): array
    {
        if ($this->productRepository->findById($id) === null) {
            return $this->notFoundFailure();
        }

        $validation = $this->validateProductInput($input);

        if (! $validation['ok']) {
            return $validation;
        }

        $product = $this->productRepository->update($id, $validation['data']);

        if ($product === null) {
            return $this->notFoundFailure();
        }

        return ['ok' => true, 'data' => $this->formatProduct($product)];
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, error: string}
     */
    public function delete(int $id): array
    {
        if ($this->productRepository->findById($id) === null) {
            return $this->notFoundFailure();
        }

        $this->productRepository->delete($id);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array{category_id: int, name: string, description: string, price: string, stock: int}}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    private function validateProductInput(array $input): array
    {
        $errors = $this->validator->validate($input, $this->productRules());
        $errors = array_merge($errors, $this->validateNumericFields($input));

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $categoryId = (int) $input['category_id'];

        if ($this->categoryRepository->findById($categoryId) === null) {
            return $this->categoryNotFoundFailure();
        }

        return [
            'ok' => true,
            'data' => [
                'category_id' => $categoryId,
                'name' => trim((string) $input['name']),
                'description' => trim((string) $input['description']),
                'price' => $this->formatPrice((float) $input['price']),
                'stock' => (int) $input['stock'],
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function productRules(): array
    {
        return [
            'category_id' => ['required'],
            'name' => ['required', 'maxLength:' . self::MAX_NAME_LENGTH],
            'description' => ['required', 'maxLength:' . self::MAX_DESCRIPTION_LENGTH],
            'price' => ['required'],
            'stock' => ['required'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validateNumericFields(array $input): array
    {
        $errors = [];

        if (isset($input['category_id']) && filter_var($input['category_id'], FILTER_VALIDATE_INT) === false) {
            $errors['category_id'] = 'category_id must be an integer';
        }

        if (isset($input['price']) && ! $this->isValidPrice($input['price'])) {
            $errors['price'] = 'price must be a positive number';
        }

        if (isset($input['stock']) && filter_var($input['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            $errors['stock'] = 'stock must be a non-negative integer';
        }

        return $errors;
    }

    private function isValidPrice(mixed $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        return (float) $value > 0;
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
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

    /**
     * @return array{ok: false, status: int, error: string}
     */
    private function categoryNotFoundFailure(): array
    {
        return ['ok' => false, 'status' => 404, 'error' => self::ERROR_CATEGORY_NOT_FOUND];
    }

    /**
     * @return array{id: int, category_id: int, name: string, description: string, price: string, stock: int, created_at: string, updated_at: string}
     */
    private function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'category_id' => $product->categoryId,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'created_at' => $product->createdAt,
            'updated_at' => $product->updatedAt,
        ];
    }
}
