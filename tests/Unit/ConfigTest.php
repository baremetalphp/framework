<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Support\Config;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset config
        $reflection = new \ReflectionClass(Config::class);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);
        $property->setValue(null, []);
        
        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue(null, false);
        
        // Set config path to baremetalphp/config directory
        // From tests/Unit, go up 3 levels to get to /Users/elliotanderson/php, then add baremetalphp/config
        $configPath = dirname(__DIR__, 3) . '/baremetalphp/config';
        Config::setConfigPath($configPath);
    }

    public function testCanSetConfigValue(): void
    {
        Config::set('app.name', 'Test App');
        
        $this->assertEquals('Test App', Config::get('app.name'));
    }

    public function testCanGetConfigValue(): void
    {
        Config::set('database.host', 'localhost');
        Config::set('database.port', 3306);
        
        $this->assertEquals('localhost', Config::get('database.host'));
        $this->assertEquals(3306, Config::get('database.port'));
    }

    public function testCanGetConfigWithDefault(): void
    {
        $value = Config::get('nonexistent.key', 'default-value');
        
        $this->assertEquals('default-value', $value);
    }

    public function testCanCheckIfConfigExists(): void
    {
        Config::set('app.name', 'Test');
        
        $this->assertTrue(Config::has('app.name'));
        $this->assertFalse(Config::has('app.nonexistent'));
    }

    public function testCanGetAllConfig(): void
    {
        Config::set('app.name', 'Test');
        Config::set('app.env', 'local');
        
        $all = Config::all();
        
        $this->assertIsArray($all);
        $this->assertArrayHasKey('app', $all);
    }

    public function testAppServerConfigDefaults(): void
    {
        // Ensure config path is set (it should be set in setUp, but ensure it's set)
        $configPath = dirname(__DIR__, 3) . '/baremetalphp/config';
        Config::setConfigPath($configPath);
        
        // Reset config state to ensure fresh load
        $reflection = new \ReflectionClass(Config::class);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);
        $property->setValue(null, []);
        
        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue(null, false);
        
        // Verify config path is set before loading
        $configPathProperty = $reflection->getProperty('configPath');
        $configPathProperty->setAccessible(true);
        try {
            $actualPath = $configPathProperty->getValue();
            if ($actualPath !== $configPath) {
                Config::setConfigPath($configPath);
            }
        } catch (\Error $e) {
            // Property not initialized, set it
            Config::setConfigPath($configPath);
        }
        
        // Force config to load
        Config::load();
        
        $config = Config::get('appserver');
        
        // Also test the helper function
        $configHelper = config('appserver');

        $this->assertIsArray($config, 'Config::get("appserver") should return an array, got: ' . gettype($config));
        $this->assertIsArray($configHelper, 'config("appserver") should return an array, got: ' . gettype($configHelper));
        $this->assertArrayHasKey('fast_workers', $config);
        $this->assertArrayHasKey('slow_workers', $config);
        $this->assertArrayHasKey('hot_reload', $config);
        $this->assertArrayHasKey('static', $config);

        $this->assertSame(4, $config['fast_workers']);
        $this->assertSame(2, $config['slow_workers']);
        $this->assertTrue($config['hot_reload']);
        $this->assertIsArray($config['static']);
        $this->assertNotEmpty($config['static']);
    }

}

