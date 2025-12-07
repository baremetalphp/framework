<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Console\MakeControllerCommand;
use Tests\TestCase;

class MakeControllerCommandTest extends TestCase
{
    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/baremetal_controller_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        chdir(sys_get_temp_dir());
        parent::tearDown();
    }

    public function testCanCreateController(): void
    {
        $command = new MakeControllerCommand();
        
        ob_start();
        $command->handle(['UserController']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Controller created', $output);
        
        $controllerPath = $this->testDir . '/app/Http/Controllers/UserController.php';
        $this->assertFileExists($controllerPath);
        
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('class UserController', $content);
        $this->assertStringContainsString('namespace App\Http\Controllers', $content);
        $this->assertStringContainsString('public function index', $content);
    }

    public function testShowsUsageWhenNoNameProvided(): void
    {
        $command = new MakeControllerCommand();
        
        ob_start();
        $command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('make:controller', $output);
    }

    public function testSkipsExistingController(): void
    {
        mkdir($this->testDir . '/app/Http/Controllers', 0755, true);
        file_put_contents($this->testDir . '/app/Http/Controllers/UserController.php', '<?php // existing');
        
        $command = new MakeControllerCommand();
        
        ob_start();
        $command->handle(['UserController']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Controller already exists', $output);
        
        // Original content should be preserved
        $content = file_get_contents($this->testDir . '/app/Http/Controllers/UserController.php');
        $this->assertStringContainsString('existing', $content);
    }

    public function testCreatesDirectoryStructure(): void
    {
        $command = new MakeControllerCommand();
        $command->handle(['TestController']);
        
        $this->assertDirectoryExists($this->testDir . '/app');
        $this->assertDirectoryExists($this->testDir . '/app/Http');
        $this->assertDirectoryExists($this->testDir . '/app/Http/Controllers');
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
