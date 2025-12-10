<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\ConnectionManager;
use Tests\TestCase;

class ConnectionManagerAdvancedTest extends TestCase
{
    public function testGetDefaultConnection(): void
    {
        $manager = new ConnectionManager();
        
        $this->assertEquals('default', $manager->getDefaultConnection());
    }

    public function testSetDefaultConnection(): void
    {
        $manager = new ConnectionManager();
        $manager->setDefaultConnection('secondary');
        
        $this->assertEquals('secondary', $manager->getDefaultConnection());
    }

    public function testGetConnections(): void
    {
        $manager = new ConnectionManager();
        $connection = new Connection('sqlite::memory:');
        
        $manager->addConnection('test', $connection);
        $connections = $manager->getConnections();
        
        $this->assertArrayHasKey('test', $connections);
        $this->assertSame($connection, $connections['test']);
    }

    public function testHasConnection(): void
    {
        $manager = new ConnectionManager();
        $connection = new Connection('sqlite::memory:');
        
        $this->assertFalse($manager->hasConnection('test'));
        
        $manager->addConnection('test', $connection);
        
        $this->assertTrue($manager->hasConnection('test'));
    }

    public function testSetConnectionConfigs(): void
    {
        $manager = new ConnectionManager();
        $configs = [
            'secondary' => [
                'driver' => 'sqlite',
                'database' => ':memory:'
            ]
        ];
        
        $manager->setConnectionConfigs($configs);
        
        // Should be able to create connection from config
        $connection = $manager->connection('secondary');
        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testCreateConnectionFromConfig(): void
    {
        $manager = new ConnectionManager();
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:'
        ];
        
        $connection = $manager->createConnection($config);
        
        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testCreateConnectionThrowsForUnsupportedDriver(): void
    {
        $manager = new ConnectionManager();
        $config = [
            'driver' => 'unsupported'
        ];
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database driver');
        
        $manager->createConnection($config);
    }

    public function testConnectionLazyLoadsFromConfig(): void
    {
        $manager = new ConnectionManager();
        $configs = [
            'lazy' => [
                'driver' => 'sqlite',
                'database' => ':memory:'
            ]
        ];
        
        $manager->setConnectionConfigs($configs);
        
        // Should create connection on first access
        $connection = $manager->connection('lazy');
        $this->assertInstanceOf(Connection::class, $connection);
        
        // Should be cached
        $connection2 = $manager->connection('lazy');
        $this->assertSame($connection, $connection2);
    }

    public function testConnectionThrowsWhenNotFound(): void
    {
        $manager = new ConnectionManager();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection [nonexistent] not found');
        
        $manager->connection('nonexistent');
    }
}

