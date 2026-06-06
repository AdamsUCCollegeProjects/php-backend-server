<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\OrderService;

final class AdminOrderController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(Request $request): Response
    {
        $result = $this->orderService->listAll();

        return Response::json(['orders' => $result['data']]);
    }

    public function show(Request $request): Response
    {
        $orderId = $this->extractOrderId($request);

        if ($orderId === null) {
            return Response::error('Invalid order id', 400);
        }

        $result = $this->orderService->getById($orderId);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json($result['data']);
    }

    public function updateStatus(Request $request): Response
    {
        $orderId = $this->extractOrderId($request);

        if ($orderId === null) {
            return Response::error('Invalid order id', 400);
        }

        $result = $this->orderService->updateStatus($orderId, $request->getBody());

        if (! $result['ok']) {
            return Response::error(
                $result['error'],
                $result['status'],
                $result['details'] ?? [],
            );
        }

        return Response::json($result['data']);
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
