<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use RuntimeException;

final class MockPayWayService implements PayWayServiceInterface
{
    private const MOCK_APPROVAL_CODE = 'MOCK-APV';
    private const PAYMENT_SUCCESS_STATUS = 0;

    /**
     * @param array{
     *     webhook_url: string,
     *     success_url_template: string,
     * } $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function createPurchase(Order $order, User $user): PayWayPurchaseResult
    {
        $tranId = $this->resolveTranId($order);
        $successUrl = $this->buildSuccessUrl($order->id);
        $webhookUrl = $this->config['webhook_url'];

        return new PayWayPurchaseResult(
            checkoutHtml: $this->buildMockCheckoutHtml($tranId, $successUrl, $webhookUrl, $order->total),
            tranId: $tranId,
        );
    }

    private function resolveTranId(Order $order): string
    {
        if ($order->paywayTranId !== null && $order->paywayTranId !== '') {
            return $order->paywayTranId;
        }

        throw new RuntimeException('Order is missing payway_tran_id for mock checkout.');
    }

    private function buildSuccessUrl(int $orderId): string
    {
        return str_replace('{orderId}', (string) $orderId, $this->config['success_url_template']);
    }

    private function buildMockCheckoutHtml(
        string $tranId,
        string $successUrl,
        string $webhookUrl,
        string $amount,
    ): string {
        $escapedTranId = htmlspecialchars($tranId, ENT_QUOTES, 'UTF-8');
        $escapedAmount = htmlspecialchars($amount, ENT_QUOTES, 'UTF-8');
        $escapedSuccessUrl = htmlspecialchars($successUrl, ENT_QUOTES, 'UTF-8');
        $escapedWebhookUrl = htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8');
        $escapedApv = htmlspecialchars(self::MOCK_APPROVAL_CODE, ENT_QUOTES, 'UTF-8');
        $successStatus = (string) self::PAYMENT_SUCCESS_STATUS;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PayWay Mock Checkout</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 2rem; background: #f4f6f8; }
        .card { max-width: 28rem; margin: 0 auto; background: #fff; border-radius: 0.5rem; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        p { color: #555; margin: 0 0 1rem; }
        button { width: 100%; padding: 0.75rem 1rem; font-size: 1rem; border: 0; border-radius: 0.375rem; background: #0057b8; color: #fff; cursor: pointer; }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        #status { margin-top: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>ABA Pay (Mock)</h1>
        <p>Transaction <strong>{$escapedTranId}</strong> — USD {$escapedAmount}</p>
        <button id="pay-btn" type="button">Simulate ABA Pay</button>
        <p id="status"></p>
    </div>
    <script>
        const payButton = document.getElementById('pay-btn');
        const statusEl = document.getElementById('status');
        payButton.addEventListener('click', async () => {
            payButton.disabled = true;
            statusEl.textContent = 'Processing payment...';
            try {
                const response = await fetch('{$escapedWebhookUrl}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tran_id: '{$escapedTranId}',
                        apv: '{$escapedApv}',
                        status: {$successStatus}
                    })
                });
                if (!response.ok) {
                    throw new Error('Webhook returned ' + response.status);
                }
                statusEl.textContent = 'Payment successful. Redirecting...';
                window.top.location.href = '{$escapedSuccessUrl}';
            } catch (error) {
                statusEl.textContent = 'Payment simulation failed: ' + error.message;
                payButton.disabled = false;
            }
        });
    </script>
</body>
</html>
HTML;
    }
}
