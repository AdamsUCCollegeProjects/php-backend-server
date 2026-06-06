<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(Request $request): Response
    {
        $result = $this->authService->register($request->getBody());

        if (! $result['ok']) {
            return Response::error(
                $result['error'],
                $result['status'],
                $result['details'] ?? [],
            );
        }

        return Response::json($result['data'], 201);
    }

    public function login(Request $request): Response
    {
        $result = $this->authService->login($request->getBody());

        if (! $result['ok']) {
            return Response::error(
                $result['error'],
                $result['status'],
                $result['details'] ?? [],
            );
        }

        return Response::json($result['data']);
    }

    public function registerAdmin(Request $request): Response
    {
        $result = $this->authService->registerAdmin($request->getBody());

        if (! $result['ok']) {
            return Response::error(
                $result['error'],
                $result['status'],
                $result['details'] ?? [],
            );
        }

        return Response::json($result['data'], 201);
    }
}
