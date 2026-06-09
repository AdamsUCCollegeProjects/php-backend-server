<?php

declare(strict_types=1);

namespace App\Services;

final class PayWayPurchaseResult
{
    public function __construct(
        public readonly string $checkoutHtml,
        public readonly string $tranId,
    ) {
    }
}
