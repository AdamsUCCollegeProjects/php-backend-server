<?php

declare(strict_types=1);

use App\Core\RouteDependencies;
use App\Core\Router;

return static function (Router $router, RouteDependencies $dependencies): void {
    $auth = [[$dependencies->authMiddleware, 'handle']];
    $admin = [
        [$dependencies->authMiddleware, 'handle'],
        [$dependencies->adminMiddleware, 'handle'],
    ];

    $router->get('/health', [$dependencies->healthController, 'check']);

    $router->post('/api/register', [$dependencies->authController, 'register']);
    $router->post('/api/admin/register', [$dependencies->authController, 'registerAdmin']);
    $router->post('/api/login', [$dependencies->authController, 'login']);

    $router->get(
        '/api/profile',
        [$dependencies->profileController, 'show'],
        $auth,
    );

    $router->get('/api/categories', [$dependencies->categoryController, 'index']);
    $router->get('/api/categories/{id}', [$dependencies->categoryController, 'show']);

    $router->post('/api/admin/categories', [$dependencies->adminCategoryController, 'store'], $admin);
    $router->put('/api/admin/categories/{id}', [$dependencies->adminCategoryController, 'update'], $admin);
    $router->delete('/api/admin/categories/{id}', [$dependencies->adminCategoryController, 'destroy'], $admin);
};
