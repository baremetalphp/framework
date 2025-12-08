<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Application;
use BareMetalPHP\Console\Application as ConsoleApplication;
use BareMetalPHP\Console\Commands\InstallFrontendCommand;
use BareMetalPHP\Console\Commands\InstallGoAppServerCommand;
use BareMetalPHP\Console\MakeControllerCommand;
use BareMetalPHP\Console\MakeMigrationCommand;
use BareMetalPHP\Console\MigrateCommand;
use BareMetalPHP\Console\MigrateRollbackCommand;
use BareMetalPHP\Console\ServeCommand;
use Tests\TestCase;

class ConsoleApplicationTest extends TestCase
{
    protected ConsoleApplication $console;

    protected function setUp(): void
    {
        parent::setUp();
        $this->console = new ConsoleApplication($this->app);
    }

    public function testCanDisplayAvailableCommands(): void
    {
        ob_start();
        $this->console->run(['mini']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Available commands:', $output);
        $this->assertStringContainsString('serve', $output);
        $this->assertStringContainsString('make:controller', $output);
        $this->assertStringContainsString('migrate', $output);
        $this->assertStringContainsString('frontend:install', $output);
        $this->assertStringContainsString('go:install', $output);
    }

    public function testShowsErrorForUnknownCommand(): void
    {
        ob_start();
        $this->console->run(['mini', 'unknown:command']);
        $output = ob_get_clean();

        $this->assertStringContainsString("Command `unknown:command` not found", $output);
        $this->assertStringContainsString('Available commands:', $output);
    }

    public function testCanExecuteCommandWithArguments(): void
    {
        // Mock MakeControllerCommand to avoid file system operations
        $mockCommand = $this->createMock(MakeControllerCommand::class);
        $mockCommand->expects($this->once())
            ->method('handle')
            ->with($this->equalTo(['TestController']));

        $this->app->instance(MakeControllerCommand::class, $mockCommand);

        // Use reflection to temporarily replace the command
        $reflection = new \ReflectionClass($this->console);
        $property = $reflection->getProperty('commands');
        $property->setAccessible(true);
        $commands = $property->getValue($this->console);
        $commands['make:controller'] = MakeControllerCommand::class;
        $property->setValue($this->console, $commands);

        ob_start();
        $this->console->run(['mini', 'make:controller', 'TestController']);
        ob_get_clean();
    }

    public function testCommandsAreRegistered(): void
    {
        $reflection = new \ReflectionClass($this->console);
        $property = $reflection->getProperty('commands');
        $property->setAccessible(true);
        $commands = $property->getValue($this->console);

        $this->assertArrayHasKey('serve', $commands);
        $this->assertArrayHasKey('make:controller', $commands);
        $this->assertArrayHasKey('migrate', $commands);
        $this->assertArrayHasKey('migrate:rollback', $commands);
        $this->assertArrayHasKey('make:migration', $commands);
        $this->assertArrayHasKey('frontend:install', $commands);
        $this->assertArrayHasKey('go:install', $commands);

        $this->assertEquals(ServeCommand::class, $commands['serve']);
        $this->assertEquals(MakeControllerCommand::class, $commands['make:controller']);
        $this->assertEquals(MigrateCommand::class, $commands['migrate']);
        $this->assertEquals(InstallFrontendCommand::class, $commands['frontend:install']);
        $this->assertEquals(InstallGoAppServerCommand::class, $commands['go:install']);
    }
}
