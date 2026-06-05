<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HealthController;
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
    $healthController = new HealthController();

    $router->get('/health', [$healthController, 'check']);

    $services = null;

    $resolveServices = static function () use (&$services): array {
        if ($services !== null) {
            return $services;
        }

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
        $authMiddleware = new AuthMiddleware($jwtService);

        $services = [
            'authController' => new AuthController($authService),
            'profileController' => new ProfileController($userService),
            'authMiddleware' => $authMiddleware,
        ];

        return $services;
    };

    $router->post('/api/register', static function (Request $request) use ($resolveServices): Response {
        return $resolveServices()['authController']->register($request);
    });

    $router->post('/api/login', static function (Request $request) use ($resolveServices): Response {
        return $resolveServices()['authController']->login($request);
    });

    $router->get('/api/profile', static function (Request $request) use ($resolveServices): Response {
        $resolved = $resolveServices();

        return $resolved['authMiddleware']->handle(
            $request,
            static fn (Request $req): Response => $resolved['profileController']->show($req),
        );
    });
};
