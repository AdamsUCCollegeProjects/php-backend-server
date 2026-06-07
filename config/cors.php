<?php

declare(strict_types=1);

$allowedOrigins = $_ENV['CORS_ALLOWED_ORIGINS']
    ?? 'http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173';

return [
    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', $allowedOrigins),
    ))),
    'allowed_methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
    'allowed_headers' => 'Authorization, Content-Type',
    'max_age' => (int) ($_ENV['CORS_MAX_AGE'] ?? 86400),
];
