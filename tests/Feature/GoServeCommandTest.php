<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use BareMetalPHP\Console\Application as ConsoleApplication;
use BareMetalPHP\Support\Config;
use BareMetalPHP\Console\Commands\GoServeCommand;

class GoServeCommandTest extends TestCase
{
    protected ConsoleApplication $console;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->console = new ConsoleApplication($this->app);
        
        // Set config path to baremetalphp/config directory
        $configPath = dirname(__DIR__, 2) . '/baremetalphp/config';
        Config::setConfigPath($configPath);
        
        // Reset config
        $reflection = new \ReflectionClass(Config::class);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);
        $property->setValue(null, []);
        
        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue(null, false);
    }
    public function testDryRunShowsCommand(): void
    {
        // Enable app server for testing - set BEFORE resetting config
        putenv('APPSERVER_ENABLED=true');
        $_ENV['APPSERVER_ENABLED'] = 'true';
        $_SERVER['APPSERVER_ENABLED'] = 'true';
        
        // Reset config to reload environment variables
        $this->resetConfig();
        
        // Force config to load and then override with our test value
        Config::get('appserver'); // This loads the config
        Config::set('appserver.enabled', true); // Override with our test value

        $cmd = new GoServeCommand();

        ob_start();
        $cmd->handle(['--dry-run']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Go app server (dry run)', $output);
        $this->assertStringContainsString('go run ./cmd/server', $output);
    }

    public function testShowsDisabledMessageWhenNotEnabled(): void
    {
        putenv('APPSERVER_ENABLED=false');
        $_ENV['APPSERVER_ENABLED'] = 'false';
        $_SERVER['APPSERVER_ENABLED'] = 'false';
        
        // Reset config to reload environment variables
        $this->resetConfig();

        $cmd = new GoServeCommand();

        ob_start();
        $cmd->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Go app server is disabled', $output);
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('APPSERVER_ENABLED');
        unset($_ENV['APPSERVER_ENABLED'], $_SERVER['APPSERVER_ENABLED']);
        
        parent::tearDown();
    }

    protected function resetConfig(): void
    {
        // Set config path
        $configPath = dirname(__DIR__, 2) . '/baremetalphp/config';
        Config::setConfigPath($configPath);
        
        // Reset config
        $reflection = new \ReflectionClass(Config::class);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue(null, []);
        
        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue(null, false);
    }

    protected function runMiniCommand(string $command, array $args = []): string
    {
        $argv = array_merge(['mini', $command], $args);
        
        ob_start();
        $this->console->run($argv);
        return ob_get_clean();
    }

    public function testGoServeShowsDisabledMessageWhenNotEnabled(): void
    {
        putenv('APPSERVER_ENABLED=0');
        $_ENV['APPSERVER_ENABLED'] = '0';
        $_SERVER['APPSERVER_ENABLED'] = '0';
        
        // Reset config to reload environment variables
        $this->resetConfig();

        $output = $this->runMiniCommand('go:serve');

        $this->assertStringContainsString(
            'Go app server is disabled',
            $output
        );
    }

    public function testGoServeDryRunPrintsCommand(): void
    {
        putenv('APPSERVER_ENABLED=1');
        $_ENV['APPSERVER_ENABLED'] = '1';
        $_SERVER['APPSERVER_ENABLED'] = '1';
        
        // Reset config to reload environment variables
        $this->resetConfig();
        
        // Force config to load and then override with our test value
        // This ensures the config is loaded and we can override the enabled value
        Config::get('appserver'); // This loads the config
        Config::set('appserver.enabled', true); // Override with our test value

        $output = $this->runMiniCommand('go:serve', ['--dry-run']);

        $this->assertStringContainsString('Go app server (dry run): would execute `go run ./cmd/server`', $output);
    }
}