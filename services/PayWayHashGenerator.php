<?php

declare(strict_types=1);

namespace App\Services;

final class PayWayHashGenerator
{
    /** @var list<string> */
    private const HASH_FIELD_ORDER = [
        'req_time',
        'merchant_id',
        'tran_id',
        'amount',
        'items',
        'lifetime',
        'ctid',
        'pwt',
        'firstname',
        'lastname',
        'email',
        'phone',
        'type',
        'payment_option',
        'return_url',
        'cancel_url',
        'continue_success_url',
        'return_deeplink',
        'custom_fields',
        'return_params',
    ];

    /**
     * @param array<string, string> $fields
     */
    public function generate(array $fields, string $publicKey): string
    {
        $concatenated = $this->concatenateFields($fields);

        return base64_encode(hash_hmac('sha512', $concatenated, $publicKey, true));
    }

    /**
     * @param array<string, string> $fields
     */
    private function concatenateFields(array $fields): string
    {
        $parts = [];

        foreach (self::HASH_FIELD_ORDER as $fieldName) {
            $parts[] = $fields[$fieldName] ?? '';
        }

        return implode('', $parts);
    }
}
