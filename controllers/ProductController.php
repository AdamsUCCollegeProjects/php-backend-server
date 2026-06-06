<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\ProductService;

final class ProductController
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function index(Request $request): Response
    {
        $categoryId = $this->extractCategoryFilter($request);
        $result = $this->productService->listAll($categoryId);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json(['products' => $result['data']]);
    }

    public function show(Request $request): Response
    {
        $productId = $this->extractProductId($request);

        if ($productId === null) {
            return Response::error('Invalid product id', 400);
        }

        $result = $this->productService->getById($productId);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json($result['data']);
    }

    private function extractCategoryFilter(Request $request): ?int
    {
        $categoryId = $request->getQueryValue('category_id');

        if ($categoryId === null) {
            return null;
        }

        $parsed = filter_var($categoryId, FILTER_VALIDATE_INT);

        return $parsed !== false ? $parsed : null;
    }

    private function extractProductId(Request $request): ?int
    {
        $params = $request->getAttribute(Router::ATTRIBUTE_ROUTE_PARAMS, []);

        if (! is_array($params) || ! isset($params['id'])) {
            return null;
        }

        $id = filter_var($params['id'], FILTER_VALIDATE_INT);

        return $id !== false ? $id : null;
    }
}
