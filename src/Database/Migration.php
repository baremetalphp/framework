<?php

declare(strict_types=1);

namespace BareMetalPHP\Database;

use BareMetalPHP\Database\Schema\Blueprint;

abstract class Migration
{
    abstract public function up(Connection $connection): void;
    abstract public function down(Connection $connection): void;

    /**
     * Create a table with driver-aware SQL using the Schema Builder
     */
    protected function schema(Connection $connection, callable $callback): void
    {
        $driver = $connection->getDriver();
        $callback(new Schema($connection));
    }

    /**
     * Create a table with driver-aware SQL
     */
    protected function createTable(Connection $connection, string $table, callable $callback): void
    {
        $driver = $connection->getDriver();
        $pdo = $connection->pdo();
        
        $blueprint = new Blueprint($driver, $table);
        $callback($blueprint);
        
        $sql = $blueprint->toSql();
        $pdo->exec($sql);
    }

    /**
     * Drop a table
     */
    protected function dropTable(Connection $connection, string $table): void
    {
        $driver = $connection->getDriver();
        $pdo = $connection->pdo();
        
        // For SQLite, ensure we're not in a transaction and set busy timeout
        if ($driver->getName() === 'sqlite') {
            $pdo->exec("PRAGMA busy_timeout = 30000");
            // Ensure we're not in a transaction
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            // Close any open statements to release locks
            // This is a workaround for SQLite locking issues
            try {
                $pdo->query("SELECT 1")->closeCursor();
            } catch (\PDOException $e) {
                // Ignore errors from this cleanup
            }
        }
        
        $quotedTable = $driver->quoteIdentifier($table);
        
        // Retry logic for SQLite locks
        $maxRetries = 5;
        $retry = 0;
        while ($retry < $maxRetries) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS {$quotedTable}");
                break;
            } catch (\PDOException $e) {
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
                // Check for SQLite lock error (code 6 or message contains 'locked')
                $isLockError = $errorCode == 6 || str_contains($errorMessage, 'locked') || str_contains($errorMessage, 'SQLITE_LOCKED');
                
                if ($isLockError && $retry < $maxRetries - 1) {
                    $retry++;
                    // Exponential backoff: 50ms, 100ms, 200ms, 400ms
                    usleep(50000 * (2 ** ($retry - 1)));
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Modify an existing table
     */
    protected function table(Connection $connection, string $table, callable $callback): void
    {
        $driver = $connection->getDriver();
        $pdo = $connection->pdo();
        
        $blueprint = new Blueprint($driver, $table);
        $blueprint->isAlter = true;
        $callback($blueprint);
        
        $sql = $blueprint->toAlterSql();
        if ($sql) {
            $pdo->exec($sql);
        }
    }
}

/**
 * Schema helper for building database schemas
 */
class Schema
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create a new table
     */
    public function create(string $table, callable $callback): void
    {
        $driver = $this->connection->getDriver();
        $pdo = $this->connection->pdo();
        
        $blueprint = new Blueprint($driver, $table);
        $callback($blueprint);
        
        $sql = $blueprint->toSql();
        $pdo->exec($sql);
    }

    /**
     * Modify an existing table
     */
    public function table(string $table, callable $callback): void
    {
        $driver = $this->connection->getDriver();
        $pdo = $this->connection->pdo();
        
        $blueprint = new Blueprint($driver, $table);
        $blueprint->isAlter = true;
        $callback($blueprint);
        
        $sql = $blueprint->toAlterSql();
        if ($sql) {
            $pdo->exec($sql);
        }
    }

    /**
     * Drop a table
     */
    public function drop(string $table): void
    {
        $driver = $this->connection->getDriver();
        $pdo = $this->connection->pdo();
        
        $quotedTable = $driver->quoteIdentifier($table);
        $pdo->exec("DROP TABLE IF EXISTS {$quotedTable}");
    }

    /**
     * Drop a table if it exists
     */
    public function dropIfExists(string $table): void
    {
        $this->drop($table);
    }

    /**
     * Rename a table
     */
    public function rename(string $from, string $to): void
    {
        $driver = $this->connection->getDriver();
        $pdo = $this->connection->pdo();
        
        $quotedFrom = $driver->quoteIdentifier($from);
        $quotedTo = $driver->quoteIdentifier($to);
        $pdo->exec("ALTER TABLE {$quotedFrom} RENAME TO {$quotedTo}");
    }
}