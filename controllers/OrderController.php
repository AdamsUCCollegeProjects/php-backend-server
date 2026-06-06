<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Services\OrderService;

final class OrderController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(Request $request): Response
    {
        $userId = $this->extractUserId($request);

        if ($userId === null) {
            return Response::error('Unauthorized', 401);
        }

        $result = $this->orderService->listByUser($userId);

        return Response::json(['orders' => $result['data']]);
    }

    public function show(Request $request): Response
    {
        $userId = $this->extractUserId($request);

        if ($userId === null) {
            return Response::error('Unauthorized', 401);
        }

        $orderId = $this->extractOrderId($request);

        if ($orderId === null) {
            return Response::error('Invalid order id', 400);
        }

        $result = $this->orderService->getByIdForUser($userId, $orderId);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json($result['data']);
    }

    private function extractUserId(Request $request): ?int
    {
        $userId = $request->getAttribute(AuthMiddleware::ATTRIBUTE_USER_ID);

        return is_int($userId) ? $userId : null;
    }

    private function extractOrderId(Request $request): ?int
    {
        $params = $request->getAttribute(Router::ATTRIBUTE_ROUTE_PARAMS, []);

        if (! is_array($params) || ! isset($params['id'])) {
            return null;
        }

        $id = filter_var($params['id'], FILTER_VALIDATE_INT);

        return $id !== false ? $id : null;
    }
}
