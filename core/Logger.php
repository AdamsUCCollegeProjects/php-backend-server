<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

final class Logger
{
    private static ?MonologLogger $instance = null;

    public static function getInstance(): MonologLogger
    {
        if (self::$instance instanceof MonologLogger) {
            return self::$instance;
        }

        /** @var array{env: string, debug: bool} $config */
        $config = require __DIR__ . '/../config/app.php';

        $level = $config['debug'] ? Level::Debug : Level::Info;

        $logger = new MonologLogger('app');
        $logger->pushHandler(new StreamHandler('php://stdout', $level));

        self::$instance = $logger;

        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
