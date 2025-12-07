<?php

declare(strict_types=1);

namespace Tests\Feature;

use BareMetalPHP\Console\Commands\InstallGoAppServerCommand;
use Tests\TestCase;

class InstallGoAppServerCommandTest extends TestCase
{
    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a temporary directory for testing
        $this->testDir = sys_get_temp_dir() . '/baremetal_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
        
        // Change to test directory
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        $this->removeDirectory($this->testDir);
        
        // Change back to original directory
        chdir(sys_get_temp_dir());
        
        parent::tearDown();
    }

    public function testCanInstallGoServerFiles(): void
    {
        $command = new InstallGoAppServerCommand();
        
        ob_start();
        $command->handle([]);
        $output = ob_get_clean();

        // Check output
        $this->assertStringContainsString('Installing Go app server', $output);
        $this->assertStringContainsString('Go app server scaffolding complete', $output);
        $this->assertStringContainsString('go mod tidy', $output);

        // Check go.mod was created
        $this->assertFileExists($this->testDir . '/go.mod');
        
        $goModContent = file_get_contents($this->testDir . '/go.mod');
        $this->assertStringContainsString('module', $goModContent);
        $this->assertStringContainsString('github.com/google/uuid', $goModContent);
        $this->assertStringContainsString('github.com/fsnotify/fsnotify', $goModContent);

        // Check config file was created
        $this->assertFileExists($this->testDir . '/go_appserver.json');
        
        $config = json_decode(file_get_contents($this->testDir . '/go_appserver.json'), true);
        $this->assertIsArray($config);
        $this->assertEquals(4, $config['fast_workers']);
        $this->assertEquals(2, $config['slow_workers']);
        $this->assertTrue($config['hot_reload']);
        $this->assertIsArray($config['static']);

        // Check Go files were created
        $this->assertFileExists($this->testDir . '/cmd/server/main.go');
        $this->assertFileExists($this->testDir . '/cmd/server/config.go');
        $this->assertFileExists($this->testDir . '/server/server.go');
        $this->assertFileExists($this->testDir . '/server/worker.go');
        $this->assertFileExists($this->testDir . '/server/pool.go');
        $this->assertFileExists($this->testDir . '/server/payload.go');

        // Check PHP files were created
        $this->assertFileExists($this->testDir . '/php/worker.php');
        $this->assertFileExists($this->testDir . '/php/bridge.php');
        $this->assertFileExists($this->testDir . '/php/bootstrap_app.php');
    }

    public function testSkipsExistingFiles(): void
    {
        // Create existing files
        file_put_contents($this->testDir . '/go.mod', 'module existing');
        file_put_contents($this->testDir . '/go_appserver.json', '{}');
        mkdir($this->testDir . '/cmd/server', 0755, true);
        file_put_contents($this->testDir . '/cmd/server/main.go', '// existing');

        $command = new InstallGoAppServerCommand();
        
        ob_start();
        $command->handle([]);
        $output = ob_get_clean();

        // Should skip existing files
        $this->assertStringContainsString('go.mod already exists', $output);
        $this->assertStringContainsString('go_appserver.json already exists', $output);
        $this->assertStringContainsString('main.go already exists', $output);

        // Original content should be preserved
        $this->assertEquals('module existing', file_get_contents($this->testDir . '/go.mod'));
        $this->assertEquals('// existing', file_get_contents($this->testDir . '/cmd/server/main.go'));
    }

    public function testGoFilesContainCorrectContent(): void
    {
        $command = new InstallGoAppServerCommand();
        $command->handle([]);

        // Check main.go has expected content
        $mainGo = file_get_contents($this->testDir . '/cmd/server/main.go');
        $this->assertStringContainsString('package main', $mainGo);
        $this->assertStringContainsString('func main()', $mainGo);
        $this->assertStringContainsString('http.ListenAndServe', $mainGo);

        // Check config.go has expected content
        $configGo = file_get_contents($this->testDir . '/cmd/server/config.go');
        $this->assertStringContainsString('package main', $configGo);
        $this->assertStringContainsString('AppServerConfig', $configGo);
        $this->assertStringContainsString('loadConfig', $configGo);

        // Check server.go has expected content
        $serverGo = file_get_contents($this->testDir . '/server/server.go');
        $this->assertStringContainsString('package server', $serverGo);
        $this->assertStringContainsString('type Server struct', $serverGo);
        $this->assertStringContainsString('EnableHotReload', $serverGo);

        // Check worker.go has expected content
        $workerGo = file_get_contents($this->testDir . '/server/worker.go');
        $this->assertStringContainsString('package server', $workerGo);
        $this->assertStringContainsString('type Worker struct', $workerGo);
        $this->assertStringContainsString('NewWorker', $workerGo);

        // Check pool.go has expected content
        $poolGo = file_get_contents($this->testDir . '/server/pool.go');
        $this->assertStringContainsString('package server', $poolGo);
        $this->assertStringContainsString('WorkerPool', $poolGo);

        // Check payload.go has expected content
        $payloadGo = file_get_contents($this->testDir . '/server/payload.go');
        $this->assertStringContainsString('package server', $payloadGo);
        $this->assertStringContainsString('RequestPayload', $payloadGo);
        $this->assertStringContainsString('ResponsePayload', $payloadGo);
    }

    public function testPhpFilesContainCorrectContent(): void
    {
        $command = new InstallGoAppServerCommand();
        $command->handle([]);

        // Check worker.php
        $workerPhp = file_get_contents($this->testDir . '/php/worker.php');
        $this->assertStringContainsString('<?php', $workerPhp);
        $this->assertStringContainsString('bootstrap_app.php', $workerPhp);
        $this->assertStringContainsString('bridge.php', $workerPhp);
        $this->assertStringContainsString('handle_bridge_request', $workerPhp);

        // Check bridge.php
        $bridgePhp = file_get_contents($this->testDir . '/php/bridge.php');
        $this->assertStringContainsString('<?php', $bridgePhp);
        $this->assertStringContainsString('build_server_array', $bridgePhp);
        $this->assertStringContainsString('make_baremetal_request', $bridgePhp);
        $this->assertStringContainsString('handle_bridge_request', $bridgePhp);

        // Check bootstrap_app.php
        $bootstrapPhp = file_get_contents($this->testDir . '/php/bootstrap_app.php');
        $this->assertStringContainsString('<?php', $bootstrapPhp);
        $this->assertStringContainsString('vendor/autoload.php', $bootstrapPhp);
        $this->assertStringContainsString('Application', $bootstrapPhp);
        $this->assertStringContainsString('Kernel', $bootstrapPhp);
    }

    public function testGoModUsesCorrectModuleName(): void
    {
        $command = new InstallGoAppServerCommand();
        $command->handle([]);

        $moduleName = basename($this->testDir);
        $goModContent = file_get_contents($this->testDir . '/go.mod');
        
        $this->assertStringContainsString("module {$moduleName}", $goModContent);
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
