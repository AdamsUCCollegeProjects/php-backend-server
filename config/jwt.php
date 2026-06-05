<?php

declare(strict_types=1);

return [
    'secret' => $_ENV['JWT_SECRET'] ?? '',
    'ttl_seconds' => (int) ($_ENV['JWT_TTL_SECONDS'] ?? 3600),
    'algorithm' => 'HS256',
];
