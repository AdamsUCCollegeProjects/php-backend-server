<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AdminDashboardService;

final class AdminDashboardController
{
    public function __construct(private readonly AdminDashboardService $adminDashboardService)
    {
    }

    public function show(Request $request): Response
    {
        $result = $this->adminDashboardService->getSummary();

        return Response::json($result['data']);
    }
}
