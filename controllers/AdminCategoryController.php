<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\CategoryService;

final class AdminCategoryController
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }

    public function store(Request $request): Response
    {
        $result = $this->categoryService->create($request->getBody());

        if (! $result['ok']) {
            return Response::error(
                $result['error'],
                $result['status'],
                $result['details'] ?? [],
            );
        }

        return Response::json($result['data'], 201);
    }

    public function update(Request $request): Response
    {
        $categoryId = $this->extractCategoryId($request);

        if ($categoryId === null) {
            return Response::error('Invalid category id', 400);
        }

        $result = $this->categoryService->update($categoryId, $request->getBody());

        if (! $result['ok']) {
            return Response::error(
                $result['error'],
                $result['status'],
                $result['details'] ?? [],
            );
        }

        return Response::json($result['data']);
    }

    public function destroy(Request $request): Response
    {
        $categoryId = $this->extractCategoryId($request);

        if ($categoryId === null) {
            return Response::error('Invalid category id', 400);
        }

        $result = $this->categoryService->delete($categoryId);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json(['message' => 'Category deleted']);
    }

    private function extractCategoryId(Request $request): ?int
    {
        $params = $request->getAttribute(Router::ATTRIBUTE_ROUTE_PARAMS, []);

        if (! is_array($params) || ! isset($params['id'])) {
            return null;
        }

        $id = filter_var($params['id'], FILTER_VALIDATE_INT);

        return $id !== false ? $id : null;
    }
}
