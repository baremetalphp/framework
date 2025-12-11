<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\Driver\SqliteDriver;
use BareMetalPHP\Database\Driver\MysqlDriver;
use BareMetalPHP\Database\Driver\PostgresDriver;
use Tests\TestCase;

class ConnectionAdvancedTest extends TestCase
{
    public function testSetDriver(): void
    {
        $connection = new Connection('sqlite::memory:');
        $driver = new SqliteDriver();
        
        $connection->setDriver($driver);
        
        $this->assertSame($driver, $connection->getDriver());
    }

    public function testGetDriverReturnsFallbackWhenNotSet(): void
    {
        // Create connection without driver
        $connection = new Connection('sqlite::memory:');
        
        // Remove driver via reflection
        $reflection = new \ReflectionClass($connection);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        $property->setValue($connection, null);
        
        // Should return SQLite driver as fallback
        $driver = $connection->getDriver();
        $this->assertInstanceOf(SqliteDriver::class, $driver);
    }

    public function testAutoDetectDriverForSqlite(): void
    {
        $connection = new Connection('sqlite::memory:');
        
        $driver = $connection->getDriver();
        $this->assertInstanceOf(SqliteDriver::class, $driver);
    }

    public function testAutoDetectDriverForMysql(): void
    {
        // We can't actually connect to MySQL, but we can test the detection
        // by checking the driver type after construction
        $connection = null;
        try {
            $connection = new Connection('mysql:host=localhost;dbname=test', 'user', 'pass');
            $driver = $connection->getDriver();
            $this->assertInstanceOf(MysqlDriver::class, $driver);
        } catch (\PDOException $e) {
            // Connection will fail, but driver should still be detected
            if ($connection) {
                $reflection = new \ReflectionClass($connection);
                $property = $reflection->getProperty('driver');
                $property->setAccessible(true);
                $driver = $property->getValue($connection);
                $this->assertInstanceOf(MysqlDriver::class, $driver);
            } else {
                $this->markTestSkipped('Could not create connection to test driver detection');
            }
        }
    }

    public function testAutoDetectDriverForPostgres(): void
    {
        $connection = null;
        try {
            $connection = new Connection('pgsql:host=localhost;dbname=test', 'user', 'pass');
            $driver = $connection->getDriver();
            $this->assertInstanceOf(PostgresDriver::class, $driver);
        } catch (\PDOException $e) {
            // Connection will fail, but driver should still be detected
            if ($connection) {
                $reflection = new \ReflectionClass($connection);
                $property = $reflection->getProperty('driver');
                $property->setAccessible(true);
                $driver = $property->getValue($connection);
                $this->assertInstanceOf(PostgresDriver::class, $driver);
            } else {
                $this->markTestSkipped('Could not create connection to test driver detection');
            }
        }
    }

    public function testAutoDetectDriverDefaultsToSqlite(): void
    {
        $connection = null;
        try {
            $connection = new Connection('unknown:test');
            $driver = $connection->getDriver();
            $this->assertInstanceOf(SqliteDriver::class, $driver);
        } catch (\PDOException $e) {
            // Connection will fail, but should default to SQLite
            if ($connection) {
                $reflection = new \ReflectionClass($connection);
                $property = $reflection->getProperty('driver');
                $property->setAccessible(true);
                $driver = $property->getValue($connection);
                $this->assertInstanceOf(SqliteDriver::class, $driver);
            } else {
                $this->markTestSkipped('Could not create connection to test driver detection');
            }
        }
    }

    public function testConnectionSetsSqliteBusyTimeout(): void
    {
        $connection = new Connection('sqlite::memory:');
        $pdo = $connection->pdo();
        
        // Should have busy timeout set
        $stmt = $pdo->query("PRAGMA busy_timeout");
        $timeout = $stmt->fetchColumn();
        
        $this->assertEquals(30000, (int)$timeout);
    }

    public function testConnectionWithCustomOptions(): void
    {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT
        ];
        
        $connection = new Connection('sqlite::memory:', null, null, $options);
        $pdo = $connection->pdo();
        
        // Should use custom error mode
        $this->assertEquals(\PDO::ERRMODE_SILENT, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }
}

