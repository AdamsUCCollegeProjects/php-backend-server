<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\User;

interface PayWayServiceInterface
{
    public function createPurchase(Order $order, User $user): PayWayPurchaseResult;
}
