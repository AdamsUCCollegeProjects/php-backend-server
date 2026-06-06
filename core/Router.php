<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    public const ATTRIBUTE_ROUTE_PARAMS = 'routeParams';

    /** @var list<array{method: string, path: string, handler: callable, middleware: list<callable>}> */
    private array $routes = [];

    /**
     * @param callable(Request): Response $handler
     * @param list<callable(Request, callable): Response> $middleware
     */
    public function add(
        string $method,
        string $path,
        callable $handler,
        array $middleware = [],
    ): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->normalizePath($request->getPath());
        $method = $request->getMethod();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPath($route['path'], $path);

            if ($params === null) {
                continue;
            }

            if ($params !== []) {
                $request->setAttribute(self::ATTRIBUTE_ROUTE_PARAMS, $params);
            }

            return $this->runPipeline($request, $route['middleware'], $route['handler']);
        }

        return Response::error('Not found', 404);
    }

    /**
     * @return array<string, string>|null Empty array for exact match, params for pattern match, null if no match
     */
    private function matchPath(string $routePath, string $requestPath): ?array
    {
        if (! str_contains($routePath, '{')) {
            return $routePath === $requestPath ? [] : null;
        }

        $pattern = preg_replace('#\{([^}]+)\}#', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (! preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        $params = [];

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * @param list<callable(Request, callable): Response> $middleware
     * @param callable(Request): Response $handler
     */
    private function runPipeline(Request $request, array $middleware, callable $handler): Response
    {
        $next = static fn (Request $req): Response => $handler($req);

        foreach (array_reverse($middleware) as $layer) {
            $next = static fn (Request $req): Response => $layer($req, $next);
        }

        return $next($request);
    }

    private function normalizePath(string $path): string
    {
        return rtrim($path, '/') ?: '/';
    }
}
