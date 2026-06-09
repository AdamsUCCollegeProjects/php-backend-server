<?php

declare(strict_types=1);

return [
    'mock' => filter_var($_ENV['PAYWAY_MOCK'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'merchant_id' => $_ENV['PAYWAY_MERCHANT_ID'] ?? '',
    'public_key' => $_ENV['PAYWAY_PUBLIC_KEY'] ?? '',
    'checkout_url' => $_ENV['PAYWAY_CHECKOUT_URL']
        ?? 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase',
    'success_url_template' => $_ENV['PAYWAY_SUCCESS_URL']
        ?? 'http://localhost:5173/orders/{orderId}?paid=1',
    'webhook_url' => $_ENV['PAYWAY_WEBHOOK_URL']
        ?? 'http://localhost:8000/api/webhooks/payway',
    'payment_option' => 'abapay_khqr',
];
