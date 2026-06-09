<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class HttpPayWayService implements PayWayServiceInterface
{
    private const REQUEST_TIME_FORMAT = 'YmdHis';
    private const TRANSACTION_TYPE_PURCHASE = 'purchase';
    private const HTTP_HEADER_LANGUAGE = 'language: en';
    private const ERROR_MISSING_TRAN_ID = 'Order is missing payway_tran_id for PayWay checkout.';
    private const ERROR_MISSING_CREDENTIALS = 'PayWay merchant_id and public_key are required.';
    private const ERROR_CHECKOUT_FAILED = 'PayWay checkout request failed.';

    /**
     * @param array{
     *     merchant_id: string,
     *     public_key: string,
     *     checkout_url: string,
     *     success_url_template: string,
     *     webhook_url: string,
     *     payment_option: string,
     * } $config
     */
    public function __construct(
        private readonly array $config,
        private readonly PayWayHashGenerator $hashGenerator,
    ) {
    }

    public function createPurchase(Order $order, User $user): PayWayPurchaseResult
    {
        $this->assertCredentialsConfigured();

        $tranId = $this->resolveTranId($order);
        $formFields = $this->buildFormFields($order, $user, $tranId);
        $checkoutHtml = $this->postPurchase($formFields);

        return new PayWayPurchaseResult(checkoutHtml: $checkoutHtml, tranId: $tranId);
    }

    private function assertCredentialsConfigured(): void
    {
        if ($this->config['merchant_id'] === '' || $this->config['public_key'] === '') {
            throw new RuntimeException(self::ERROR_MISSING_CREDENTIALS);
        }
    }

    private function resolveTranId(Order $order): string
    {
        if ($order->paywayTranId !== null && $order->paywayTranId !== '') {
            return $order->paywayTranId;
        }

        throw new RuntimeException(self::ERROR_MISSING_TRAN_ID);
    }

    /**
     * @return array<string, string>
     */
    private function buildFormFields(Order $order, User $user, string $tranId): array
    {
        $reqTime = $this->currentRequestTime();
        $returnUrl = base64_encode($this->config['webhook_url']);
        $continueSuccessUrl = $this->buildSuccessUrl($order->id);

        $hashFields = [
            'req_time' => $reqTime,
            'merchant_id' => $this->config['merchant_id'],
            'tran_id' => $tranId,
            'amount' => $order->total,
            'type' => self::TRANSACTION_TYPE_PURCHASE,
            'payment_option' => $this->config['payment_option'],
            'return_url' => $returnUrl,
            'continue_success_url' => $continueSuccessUrl,
            'firstname' => $this->sanitizeName($user->name),
            'email' => $user->email,
        ];

        $formFields = $hashFields;
        $formFields['hash'] = $this->hashGenerator->generate($hashFields, $this->config['public_key']);

        return $formFields;
    }

    private function currentRequestTime(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format(self::REQUEST_TIME_FORMAT);
    }

    private function buildSuccessUrl(int $orderId): string
    {
        return str_replace('{orderId}', (string) $orderId, $this->config['success_url_template']);
    }

    private function sanitizeName(string $name): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9]/', '', $name);

        return $sanitized ?? '';
    }

    /**
     * @param array<string, string> $formFields
     */
    private function postPurchase(array $formFields): string
    {
        $curlHandle = curl_init($this->config['checkout_url']);

        if ($curlHandle === false) {
            throw new RuntimeException(self::ERROR_CHECKOUT_FAILED);
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $formFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [self::HTTP_HEADER_LANGUAGE],
        ]);

        $responseBody = curl_exec($curlHandle);
        $httpStatus = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curlHandle);
        curl_close($curlHandle);

        if ($responseBody === false || $httpStatus < 200 || $httpStatus >= 300) {
            $message = $curlError !== '' ? $curlError : 'HTTP ' . $httpStatus;

            throw new RuntimeException(self::ERROR_CHECKOUT_FAILED . ' ' . $message);
        }

        return $responseBody;
    }
}
