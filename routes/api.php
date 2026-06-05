<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/health', static function (Request $request): Response {
        return Response::json(['status' => 'ok']);
    });
};
