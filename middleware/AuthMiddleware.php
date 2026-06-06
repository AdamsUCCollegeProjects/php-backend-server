<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\JwtService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;

final class AuthMiddleware
{
    public const ATTRIBUTE_USER_ID = 'userId';
    public const ATTRIBUTE_USER_ROLE = 'userRole';
    private const HEADER_AUTHORIZATION = 'authorization';
    private const BEARER_PREFIX = 'Bearer ';

    public function __construct(private readonly JwtService $jwtService)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return Response::error('Unauthorized', 401);
        }

        try {
            $payload = $this->jwtService->decode($token);
            $userId = $this->extractUserId($payload);
            $role = $this->extractRole($payload);

            if ($userId === null || $role === null) {
                return Response::error('Unauthorized', 401);
            }

            $request->setAttribute(self::ATTRIBUTE_USER_ID, $userId);
            $request->setAttribute(self::ATTRIBUTE_USER_ROLE, $role);

            return $next($request);
        } catch (ExpiredException | SignatureInvalidException | UnexpectedValueException) {
            return Response::error('Unauthorized', 401);
        }
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->getHeader(self::HEADER_AUTHORIZATION);

        if ($header === null || ! str_starts_with($header, self::BEARER_PREFIX)) {
            return null;
        }

        $token = trim(substr($header, strlen(self::BEARER_PREFIX)));

        return $token !== '' ? $token : null;
    }

    private function extractUserId(object $payload): ?int
    {
        if (! property_exists($payload, 'sub')) {
            return null;
        }

        $userId = $payload->sub;

        if (! is_int($userId) && ! is_string($userId)) {
            return null;
        }

        return (int) $userId;
    }

    private function extractRole(object $payload): ?string
    {
        if (! property_exists($payload, 'role') || ! is_string($payload->role)) {
            return null;
        }

        return $payload->role;
    }
}
