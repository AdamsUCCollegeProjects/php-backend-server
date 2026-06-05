<?php

declare(strict_types=1);

use App\Core\ExceptionHandler;
use App\Core\Request;
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
    ExceptionHandler::handle($exception);
}
