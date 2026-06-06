<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\ProductService;

final class AdminProductController
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function store(Request $request): Response
    {
        $result = $this->productService->create($request->getBody());

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
        $productId = $this->extractProductId($request);

        if ($productId === null) {
            return Response::error('Invalid product id', 400);
        }

        $result = $this->productService->update($productId, $request->getBody());

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
        $productId = $this->extractProductId($request);

        if ($productId === null) {
            return Response::error('Invalid product id', 400);
        }

        $result = $this->productService->delete($productId);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json(['message' => 'Product deleted']);
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
