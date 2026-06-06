<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminStatsRepository;

final class AdminDashboardService
{
    private const RECENT_ORDERS_LIMIT = 5;

    public function __construct(
        private readonly AdminStatsRepository $adminStatsRepository,
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}
     */
    public function getSummary(): array
    {
        $recentOrders = $this->adminStatsRepository->findRecentOrders(self::RECENT_ORDERS_LIMIT);

        return [
            'ok' => true,
            'data' => [
                'total_orders' => $this->adminStatsRepository->countOrders(),
                'total_revenue' => $this->adminStatsRepository->sumRevenue(),
                'total_users' => $this->adminStatsRepository->countUsers(),
                'total_products' => $this->adminStatsRepository->countProducts(),
                'recent_orders' => $this->orderService->formatAdminOrderSummaries($recentOrders),
            ],
        ];
    }
}
