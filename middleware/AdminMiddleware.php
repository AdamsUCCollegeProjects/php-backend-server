<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Models\User;

final class AdminMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $role = $request->getAttribute(AuthMiddleware::ATTRIBUTE_USER_ROLE);

        if ($role !== User::ROLE_ADMIN) {
            return Response::error('Forbidden', 403);
        }

        return $next($request);
    }
}
