<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ProfileController;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Validator;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\UserService;

return static function (Router $router): void {
    $pdo = Database::getConnection();
    $userRepository = new UserRepository($pdo);
    $validator = new Validator();
    $logger = Logger::getInstance();

    /** @var array{secret: string, ttl_seconds: int, algorithm: string} $jwtConfig */
    $jwtConfig = require dirname(__DIR__) . '/config/jwt.php';

    $jwtService = new JwtService(
        $jwtConfig['secret'],
        $jwtConfig['ttl_seconds'],
        $jwtConfig['algorithm'],
    );

    $authService = new AuthService($userRepository, $jwtService, $validator, $logger);
    $userService = new UserService($userRepository);

    $authController = new AuthController($authService);
    $profileController = new ProfileController($userService);
    $authMiddleware = new AuthMiddleware($jwtService);

    $router->get('/health', static function (Request $request): Response {
        return Response::json(['status' => 'ok']);
    });

    $router->post('/api/register', [$authController, 'register']);
    $router->post('/api/login', [$authController, 'login']);
    $router->get('/api/profile', [$profileController, 'show'], [[$authMiddleware, 'handle']]);
};
