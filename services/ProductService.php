<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\Product;
use App\Repositories\CategoryRepository;
use App\Repositories\FileRepository;
use App\Repositories\ProductRepository;

final class ProductService
{
    private const MAX_NAME_LENGTH = 200;
    private const MAX_DESCRIPTION_LENGTH = 5000;
    private const FILE_URL_PREFIX = '/api/files/';
    private const THUMBNAIL_URL_SUFFIX = '/thumbnail';
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    private const ERROR_VALIDATION = 'Validation failed';
    private const ERROR_NOT_FOUND = 'Product not found';
    private const ERROR_CATEGORY_NOT_FOUND = 'Category not found';
    private const ERROR_FILE_NOT_FOUND = 'Image file not found';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly FileRepository $fileRepository,
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

        $imageFileId = $this->resolveImageFileId($input, null);

        if (is_array($imageFileId)) {
            return $imageFileId;
        }

        $product = $this->productRepository->create($validation['data'] + ['image_file_id' => $imageFileId]);

        return ['ok' => true, 'data' => $this->formatProduct($product)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->productRepository->findById($id);

        if ($existing === null) {
            return $this->notFoundFailure();
        }

        $validation = $this->validateProductInput($input);

        if (! $validation['ok']) {
            return $validation;
        }

        $imageFileId = $this->resolveImageFileId($input, $existing->imageFileId);

        if (is_array($imageFileId)) {
            return $imageFileId;
        }

        $product = $this->productRepository->update(
            $id,
            $validation['data'] + ['image_file_id' => $imageFileId],
        );

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
     * @param array<string, mixed> $input
     * @return string|null|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    private function resolveImageFileId(array $input, ?string $existingImageFileId): string|null|array
    {
        if (! array_key_exists('image_file_id', $input)) {
            return $existingImageFileId;
        }

        $rawValue = $input['image_file_id'];

        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        $fileId = trim((string) $rawValue);

        if (! preg_match(self::UUID_PATTERN, $fileId)) {
            return $this->validationFailure(['image_file_id' => 'image_file_id must be a valid UUID']);
        }

        if (! $this->fileRepository->exists($fileId)) {
            return ['ok' => false, 'status' => 404, 'error' => self::ERROR_FILE_NOT_FOUND];
        }

        return $fileId;
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
     * @return array<string, mixed>
     */
    private function formatProduct(Product $product): array
    {
        $payload = [
            'id' => $product->id,
            'category_id' => $product->categoryId,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'image_file_id' => $product->imageFileId,
            'created_at' => $product->createdAt,
            'updated_at' => $product->updatedAt,
        ];

        if ($product->imageFileId === null) {
            return $payload;
        }

        $payload['image_url'] = self::FILE_URL_PREFIX . $product->imageFileId;
        $payload['thumbnail_url'] = self::FILE_URL_PREFIX . $product->imageFileId . self::THUMBNAIL_URL_SUFFIX;

        return $payload;
    }
}
