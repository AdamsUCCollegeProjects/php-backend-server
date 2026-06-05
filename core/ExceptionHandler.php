<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class ExceptionHandler
{
    private const ERROR_INTERNAL = 'Internal server error';

    public static function handle(Throwable $exception): void
    {
        Logger::getInstance()->error('Unhandled exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        $details = self::buildDebugDetails($exception);

        Response::error(self::ERROR_INTERNAL, 500, $details)->send();
    }

    /**
     * @return array<string, string|int>
     */
    private static function buildDebugDetails(Throwable $exception): array
    {
        /** @var array{debug: bool} $appConfig */
        $appConfig = require __DIR__ . '/../config/app.php';

        if (! $appConfig['debug']) {
            return [];
        }

        return [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }
}
