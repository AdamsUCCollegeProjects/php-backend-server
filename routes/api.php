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

    $router->get('/api/products', [$dependencies->productController, 'index']);
    $router->get('/api/products/{id}', [$dependencies->productController, 'show']);

    $router->post('/api/admin/products', [$dependencies->adminProductController, 'store'], $admin);
    $router->put('/api/admin/products/{id}', [$dependencies->adminProductController, 'update'], $admin);
    $router->delete('/api/admin/products/{id}', [$dependencies->adminProductController, 'destroy'], $admin);

    $router->get('/api/cart', [$dependencies->cartController, 'show'], $auth);
    $router->post('/api/cart/items', [$dependencies->cartController, 'upsertItem'], $auth);
    $router->delete('/api/cart/items/{productId}', [$dependencies->cartController, 'removeItem'], $auth);

    $router->post('/api/checkout', [$dependencies->checkoutController, 'store'], $auth);

    $router->get('/api/orders', [$dependencies->orderController, 'index'], $auth);
    $router->get('/api/orders/{id}', [$dependencies->orderController, 'show'], $auth);
};
