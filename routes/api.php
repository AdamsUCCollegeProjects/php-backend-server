<?php

declare(strict_types=1);

use App\Core\RouteDependencies;
use App\Core\Router;

return static function (Router $router, RouteDependencies $dependencies): void {
    $router->get('/health', [$dependencies->healthController, 'check']);

    $router->post('/api/register', [$dependencies->authController, 'register']);
    $router->post('/api/login', [$dependencies->authController, 'login']);

    $router->get(
        '/api/profile',
        [$dependencies->profileController, 'show'],
        [[$dependencies->authMiddleware, 'handle']],
    );
};
