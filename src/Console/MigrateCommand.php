<?php

namespace Framework\Console;

use Framework\Database\Connection;
use Framework\Database\Migration;
use PDO;

class MigrateCommand
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
        
        // Ensure migrations table exists (driver-aware)
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

        $migrationsDir = getcwd() . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0777, true);
        }

        $files = glob($migrationsDir . '/*.php');
        sort($files);

        if (!$files) {
            echo "No migration files found in database/migrations.\n";
            return;
        }

        $applied = $pdo->query('SELECT migration from migrations')->fetchAll(PDO::FETCH_COLUMN);

        $currentBatch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();
        $nextBatch = $currentBatch + 1;

        $ranSomething = false;

        foreach ($files as $file) {
            $name = basename($file);

            if (in_array($name, $applied, true)) {
                continue; // already ran
            }

            $migration = require $file;

            if (!$migration instanceof Migration) {
                echo "Skipping {$name}: file did not return a Migration instance.\n";
                continue;
            }

            echo "Migrating: {$name}...\n";

            $migration->up($connection);

            $stmt = $pdo->prepare(
                'INSERT INTO migrations (migration, batch, ran_at) VALUES (:migration, :batch, :ran_at)'
            );

            $stmt->execute([
                ':migration' => $name,
                ':batch' => $nextBatch,
                ':ran_at' => date('c'),
            ]);

            $ranSomething = true;
        }

        if (!$ranSomething) {
            echo "Nothing to migrate.\n";
        } else {
            echo "Migrations completed.\n";
        }
    }
}