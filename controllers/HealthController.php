<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDOException;

final class HealthController
{
    private const STATUS_OK = 'ok';
    private const STATUS_UNAVAILABLE = 'unavailable';
    private const DATABASE_CONNECTED = 'connected';
    private const DATABASE_DISCONNECTED = 'disconnected';

    public function check(Request $request): Response
    {
        if ($this->isDatabaseReachable()) {
            return Response::json([
                'status' => self::STATUS_OK,
                'database' => self::DATABASE_CONNECTED,
            ]);
        }

        return Response::json([
            'status' => self::STATUS_UNAVAILABLE,
            'database' => self::DATABASE_DISCONNECTED,
        ], 503);
    }

    private function isDatabaseReachable(): bool
    {
        try {
            $connection = Database::getConnection();
            $connection->query('SELECT 1');

            return true;
        } catch (PDOException) {
            Database::resetConnection();

            return false;
        }
    }
}
