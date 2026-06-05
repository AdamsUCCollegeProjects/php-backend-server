<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
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

    public function dispatch(Request $request): Response
    {
        $path = $this->normalizePath($request->getPath());
        $method = $request->getMethod();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || $route['path'] !== $path) {
                continue;
            }

            return $this->runPipeline($request, $route['middleware'], $route['handler']);
        }

        return Response::error('Not found', 404);
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
