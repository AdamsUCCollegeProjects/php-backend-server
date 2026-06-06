<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Services\CartService;

final class CartController
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function show(Request $request): Response
    {
        $userId = $this->extractUserId($request);

        if ($userId === null) {
            return Response::error('Unauthorized', 401);
        }

        $result = $this->cartService->getCart($userId);

        return Response::json($result['data']);
    }

    public function upsertItem(Request $request): Response
    {
        $userId = $this->extractUserId($request);

        if ($userId === null) {
            return Response::error('Unauthorized', 401);
        }

        $result = $this->cartService->upsertItem($userId, $request->getBody());

        if (! $result['ok']) {
            return Response::error(
                $result['error'],
                $result['status'],
                $result['details'] ?? [],
            );
        }

        return Response::json($result['data'], 201);
    }

    public function removeItem(Request $request): Response
    {
        $userId = $this->extractUserId($request);

        if ($userId === null) {
            return Response::error('Unauthorized', 401);
        }

        $productId = $this->extractProductId($request);

        if ($productId === null) {
            return Response::error('Invalid product id', 400);
        }

        $result = $this->cartService->removeItem($userId, $productId);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json(['message' => 'Cart item removed']);
    }

    private function extractUserId(Request $request): ?int
    {
        $userId = $request->getAttribute(AuthMiddleware::ATTRIBUTE_USER_ID);

        return is_int($userId) ? $userId : null;
    }

    private function extractProductId(Request $request): ?int
    {
        $params = $request->getAttribute(Router::ATTRIBUTE_ROUTE_PARAMS, []);

        if (! is_array($params) || ! isset($params['productId'])) {
            return null;
        }

        $id = filter_var($params['productId'], FILTER_VALIDATE_INT);

        return $id !== false ? $id : null;
    }
}
