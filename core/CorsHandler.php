<?php

declare(strict_types=1);

namespace App\Core;

final class CorsHandler
{
    /**
     * @param list<string> $allowedOrigins
     */
    public function __construct(
        private readonly array $allowedOrigins,
        private readonly string $allowedMethods,
        private readonly string $allowedHeaders,
        private readonly int $maxAge,
    ) {
    }

    public static function fromConfig(): self
    {
        /** @var array{
         *     allowed_origins: list<string>,
         *     allowed_methods: string,
         *     allowed_headers: string,
         *     max_age: int,
         * } $config */
        $config = require __DIR__ . '/../config/cors.php';

        return new self(
            $config['allowed_origins'],
            $config['allowed_methods'],
            $config['allowed_headers'],
            $config['max_age'],
        );
    }

    public function isPreflight(Request $request): bool
    {
        return $request->getMethod() === 'OPTIONS';
    }

    public function handlePreflight(Request $request): void
    {
        $this->applyHeaders($request);
        header('Access-Control-Max-Age: ' . (string) $this->maxAge);
        http_response_code(204);
        exit;
    }

    public function applyHeaders(Request $request): void
    {
        $origin = $request->getHeader('origin');

        if ($origin === null || ! $this->isOriginAllowed($origin)) {
            return;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: ' . $this->allowedMethods);
        header('Access-Control-Allow-Headers: ' . $this->allowedHeaders);
    }

    private function isOriginAllowed(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true);
    }
}
