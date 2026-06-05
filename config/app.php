<?php

declare(strict_types=1);

return [
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'port' => (int) ($_ENV['APP_PORT'] ?? 8000),
];
