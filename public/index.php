<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$request = Request::fromGlobals();
$router = new Router();
$registerRoutes = require dirname(__DIR__) . '/routes/api.php';
$registerRoutes($router);

try {
    $response = $router->dispatch($request);
    $response->send();
} catch (Throwable $exception) {
    $logger = Logger::getInstance();
    $logger->error('Unhandled exception', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    /** @var array{debug: bool} $appConfig */
    $appConfig = require dirname(__DIR__) . '/config/app.php';

    $payload = ['error' => 'Internal server error'];

    if ($appConfig['debug']) {
        $payload['details'] = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }

    Response::json($payload, 500)->send();
}
