<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Services\UserService;

final class ProfileController
{
    private const ERROR_NOT_FOUND = 'User not found';

    public function __construct(private readonly UserService $userService)
    {
    }

    public function show(Request $request): Response
    {
        $userId = $request->getAttribute(AuthMiddleware::ATTRIBUTE_USER_ID);

        if (! is_int($userId)) {
            return Response::error('Unauthorized', 401);
        }

        $profile = $this->userService->getProfile($userId);

        if ($profile === null) {
            return Response::error(self::ERROR_NOT_FOUND, 404);
        }

        return Response::json($profile);
    }
}
