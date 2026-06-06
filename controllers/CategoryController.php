<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\CategoryService;

final class CategoryController
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }

    public function index(Request $request): Response
    {
        $result = $this->categoryService->listAll();

        return Response::json(['categories' => $result['data']]);
    }

    public function show(Request $request): Response
    {
        $categoryId = $this->extractCategoryId($request);

        if ($categoryId === null) {
            return Response::error('Invalid category id', 400);
        }

        $result = $this->categoryService->getById($categoryId);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json($result['data']);
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
