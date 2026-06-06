<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\ProfileController;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\UserService;

final class RouteDependencies
{
    public function __construct(
        public readonly HealthController $healthController,
        public readonly AuthController $authController,
        public readonly ProfileController $profileController,
        public readonly AuthMiddleware $authMiddleware,
    ) {
    }

    public static function create(): self
    {
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

        return new self(
            new HealthController(),
            new AuthController($authService),
            new ProfileController($userService),
            new AuthMiddleware($jwtService),
        );
    }
}
