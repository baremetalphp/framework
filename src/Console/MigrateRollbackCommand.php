<?php

namespace Framework\Console;

use Framework\Database\Connection;
use Framework\Database\Migration;
use PDO;

class MigrateRollbackCommand
{
    public function handle(array $args = []): void
    {
        // Get connection from ConnectionManager if available
        $app = \Framework\Application::getInstance();
        $connection = null;
        
        if ($app) {
            try {
                $manager = $app->make(\Framework\Database\ConnectionManager::class);
                $connection = $manager->connection();
            } catch (\Exception $e) {
                // Fallback to default
            }
        }
        
        // Fallback to SQLite if ConnectionManager not available
        if (!$connection) {
            $dsn = 'sqlite:' . getcwd() . '/database.sqlite';
            $connection = new Connection($dsn);
        }
        
        $pdo = $connection->pdo();
        $driver = $connection->getDriver();
        
        // make sure migrations table exists (driver-aware)
        $quotedTable = $driver->quoteIdentifier('migrations');
        $driverName = $driver->getName();
        $idType = $driver->getAutoIncrementType();
        
        // Use VARCHAR for migration name (needed for UNIQUE constraint in MySQL)
        // SQLite can use TEXT, but VARCHAR works fine for all drivers
        $migrationType = 'VARCHAR(255)';
        
        // Driver-specific timestamp type
        if ($driverName === 'pgsql') {
            $timestampType = 'TIMESTAMP';
        } elseif ($driverName === 'mysql') {
            $timestampType = 'DATETIME';
        } else {
            $timestampType = 'TEXT'; // SQLite
        }
        
        $intType = 'INTEGER';
        
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS {$quotedTable} (
    id {$idType},
    migration {$migrationType} NOT NULL UNIQUE,
    batch {$intType} NOT NULL,
    ran_at {$timestampType} NOT NULL
);
SQL);

        $maxBatch = $pdo->query('SELECT MAX(batch) FROM migrations')->fetchColumn();

        if (!$maxBatch) {
            echo "Nothing to rollback.\n";
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT migration FROM migrations WHERE batch = :batch ORDER BY id desc'
        );
        $stmt->execute([':batch' => $maxBatch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$migrations) {
            echo "Nothing to rollback.\n";
            return;
        }

        $migrationsDir = getcwd() . '/database/migrations';

        foreach ($migrations as $name) {
            $path = $migrationsDir . '/' . $name;

            if (!file_exists($path)) {
                echo "Skipping {$name}: file not found.\n";
                continue;
            }

            $migration = require $path;

            if (!$migration instanceof Migration) {
                echo "Skipping {$name}: file did not return a Migration instance.\n";
                continue;
            }

            echo "Rolling back: {$name}...\n";

            $migration->down($connection);

            $delete = $pdo->prepare('DELETE from migrations WHERE migration = :migration');
            $delete->execute([':migration' => $name]);
        }

        echo "Rollback of batch {$maxBatch} completed.\n";

    }
}