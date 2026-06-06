<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\Category;
use App\Repositories\CategoryRepository;

final class CategoryService
{
    private const MAX_NAME_LENGTH = 100;
    private const ERROR_VALIDATION = 'Validation failed';
    private const ERROR_NOT_FOUND = 'Category not found';
    private const ERROR_DUPLICATE_NAME = 'Category name already exists';

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly Validator $validator,
    ) {
    }

    /**
     * @return array{ok: true, data: list<array<string, mixed>>}
     */
    public function listAll(): array
    {
        $categories = $this->categoryRepository->findAll();

        return [
            'ok' => true,
            'data' => array_map($this->formatCategory(...), $categories),
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string}
     */
    public function getById(int $id): array
    {
        $category = $this->categoryRepository->findById($id);

        if ($category === null) {
            return $this->notFoundFailure();
        }

        return ['ok' => true, 'data' => $this->formatCategory($category)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function create(array $input): array
    {
        $errors = $this->validator->validate($input, $this->nameRules());

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $name = trim((string) $input['name']);
        $duplicateError = $this->duplicateNameFailure($name);

        if ($duplicateError !== null) {
            return $duplicateError;
        }

        $slug = $this->generateSlug($name);
        $category = $this->categoryRepository->create($name, $slug);

        return ['ok' => true, 'data' => $this->formatCategory($category)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->categoryRepository->findById($id);

        if ($existing === null) {
            return $this->notFoundFailure();
        }

        $errors = $this->validator->validate($input, $this->nameRules());

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $name = trim((string) $input['name']);
        $duplicate = $this->categoryRepository->findByName($name);

        if ($duplicate !== null && $duplicate->id !== $id) {
            return [
                'ok' => false,
                'status' => 409,
                'error' => self::ERROR_DUPLICATE_NAME,
            ];
        }

        $slug = $this->generateSlug($name);
        $category = $this->categoryRepository->update($id, $name, $slug);

        if ($category === null) {
            return $this->notFoundFailure();
        }

        return ['ok' => true, 'data' => $this->formatCategory($category)];
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, error: string}
     */
    public function delete(int $id): array
    {
        $existing = $this->categoryRepository->findById($id);

        if ($existing === null) {
            return $this->notFoundFailure();
        }

        $this->categoryRepository->delete($id);

        return ['ok' => true];
    }

    /**
     * @return array<string, list<string>>
     */
    private function nameRules(): array
    {
        return [
            'name' => ['required', 'maxLength:' . self::MAX_NAME_LENGTH],
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

    /**
     * @return array{ok: false, status: int, error: string}|null
     */
    private function duplicateNameFailure(string $name): ?array
    {
        if ($this->categoryRepository->findByName($name) !== null) {
            return [
                'ok' => false,
                'status' => 409,
                'error' => self::ERROR_DUPLICATE_NAME,
            ];
        }

        return null;
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'category';
    }

    /**
     * @return array{id: int, name: string, slug: string, created_at: string, updated_at: string}
     */
    private function formatCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'created_at' => $category->createdAt,
            'updated_at' => $category->updatedAt,
        ];
    }
}
