<?php

declare(strict_types=1);

use App\Core\Database;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

const MIGRATIONS_DIRECTORY = __DIR__ . '/migrations';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

try {
    $pdo = Database::getConnection();
    ensureMigrationsTableExists($pdo);
    applyPendingMigrations($pdo);
    echo "Migrations complete." . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function ensureMigrationsTableExists(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS migrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    );
}

function applyPendingMigrations(PDO $pdo): void
{
    $appliedFilenames = fetchAppliedMigrationFilenames($pdo);
    $migrationFiles = listMigrationFiles();

    foreach ($migrationFiles as $filename) {
        if (in_array($filename, $appliedFilenames, true)) {
            continue;
        }

        executeMigrationFile($pdo, $filename);
        recordAppliedMigration($pdo, $filename);
        echo 'Applied: ' . $filename . PHP_EOL;
    }
}

/** @return list<string> */
function fetchAppliedMigrationFilenames(PDO $pdo): array
{
    $statement = $pdo->query('SELECT filename FROM migrations ORDER BY filename');
    $rows = $statement->fetchAll();

    return array_column($rows, 'filename');
}

/** @return list<string> */
function listMigrationFiles(): array
{
    $paths = glob(MIGRATIONS_DIRECTORY . '/*.sql');

    if ($paths === false) {
        return [];
    }

    $filenames = array_map(static fn (string $path): string => basename($path), $paths);
    sort($filenames);

    return $filenames;
}

function executeMigrationFile(PDO $pdo, string $filename): void
{
    $path = MIGRATIONS_DIRECTORY . '/' . $filename;
    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException('Unable to read migration file: ' . $filename);
    }

    $pdo->exec($sql);
}

function recordAppliedMigration(PDO $pdo, string $filename): void
{
    $statement = $pdo->prepare('INSERT INTO migrations (filename) VALUES (:filename)');
    $statement->execute(['filename' => $filename]);
}
