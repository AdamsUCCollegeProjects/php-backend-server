<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Services\OrderService;

final class CheckoutController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function store(Request $request): Response
    {
        $userId = $this->extractUserId($request);

        if ($userId === null) {
            return Response::error('Unauthorized', 401);
        }

        $result = $this->orderService->checkout($userId, $request->getBody());

        if (! $result['ok']) {
            return Response::error(
                $result['error'],
                $result['status'],
                $result['details'] ?? [],
            );
        }

        return Response::json($result['data'], 201);
    }

    private function extractUserId(Request $request): ?int
    {
        $userId = $request->getAttribute(AuthMiddleware::ATTRIBUTE_USER_ID);

        return is_int($userId) ? $userId : null;
    }
}
