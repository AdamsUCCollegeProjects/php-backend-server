<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly int $statusCode = 200,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $statusCode = 200): self
    {
        return new self($data, $statusCode);
    }

    /**
     * @param array<string, string> $details
     */
    public static function error(string $message, int $statusCode, array $details = []): self
    {
        $payload = ['error' => $message];

        if ($details !== []) {
            $payload['details'] = $details;
        }

        return new self($payload, $statusCode);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json');
        echo json_encode($this->data, JSON_UNESCAPED_SLASHES);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
