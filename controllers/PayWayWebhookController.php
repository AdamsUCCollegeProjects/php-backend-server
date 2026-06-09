<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;

final class PayWayWebhookController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function handle(Request $request): Response
    {
        $body = $request->getBody();
        $tranId = trim((string) ($body['tran_id'] ?? ''));
        $apv = trim((string) ($body['apv'] ?? ''));
        $status = $body['status'] ?? null;

        $result = $this->orderService->processPayWayWebhook($tranId, $apv, $status);

        if (! $result['ok']) {
            return Response::error($result['error'], $result['status']);
        }

        return Response::json(['status' => 'ok']);
    }
}
