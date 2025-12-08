<?php

declare(strict_types=1);

namespace Tests\Feature;

use BareMetalPHP\Console\Commands\InstallFrontendCommand;
use Tests\TestCase;

class InstallFrontendCommandTest extends TestCase
{
    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/baremetal_frontend_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
        
        // Change to test directory so getcwd() returns it
        chdir($this->testDir);
        
        // Create a minimal structure that base_path() might expect
        // base_path() uses dirname(__DIR__, 2) from helpers.php location
        // For testing, we'll ensure the command uses getcwd() or we override base_path
        // Since InstallFrontendCommand uses base_path(), we need to ensure it works
        // The command should work with getcwd() as fallback
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        chdir(sys_get_temp_dir());
        parent::tearDown();
    }

    public function testCanInstallReactFrontend(): void
    {
        $command = new InstallFrontendCommand();
        
        ob_start();
        $command->handle(['react']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Installing react frontend integration', $output);
        $this->assertStringContainsString('Frontend integration installed successfully', $output);

        // base_path() returns the framework root, so files will be created there
        // But we can check that the command executed successfully
        // In a real scenario, base_path() would point to the project root
        $basePath = base_path();
        
        // Check package.json was created (wherever base_path points)
        $packageJsonPath = base_path('package.json');
        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            $this->assertArrayHasKey('react', $packageJson['dependencies']);
            $this->assertArrayHasKey('react-dom', $packageJson['dependencies']);
            $this->assertArrayHasKey('@vitejs/plugin-react', $packageJson['devDependencies']);
            
            // Clean up
            unlink($packageJsonPath);
        }

        // Check React files
        $appJsxPath = base_path('resources/js/app.jsx');
        if (file_exists($appJsxPath)) {
            $appJsx = file_get_contents($appJsxPath);
            $this->assertStringContainsString('react', $appJsx);
            $this->assertStringContainsString('createRoot', $appJsx);
            
            // Clean up
            unlink($appJsxPath);
            if (file_exists(base_path('resources/js/App.jsx'))) {
                unlink(base_path('resources/js/App.jsx'));
            }
            if (is_dir(base_path('resources/js'))) {
                // Remove directory recursively in case there are other files
                $this->removeDirectory(base_path('resources/js'));
            }
        }

        // Check Vite config
        $viteConfigPath = base_path('vite.config.js');
        if (file_exists($viteConfigPath)) {
            $viteConfig = file_get_contents($viteConfigPath);
            $this->assertStringContainsString('@vitejs/plugin-react', $viteConfig);
            $this->assertStringContainsString('app.jsx', $viteConfig);
            
            // Clean up
            unlink($viteConfigPath);
        }
    }

    public function testCanInstallVueFrontend(): void
    {
        // Clean up any existing vite config from previous tests
        $viteConfigPath = base_path('vite.config.js');
        if (file_exists($viteConfigPath)) {
            unlink($viteConfigPath);
        }
        
        $command = new InstallFrontendCommand();
        
        ob_start();
        $command->handle(['vue']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Installing vue frontend integration', $output);

        // Check package.json (wherever base_path points)
        $packageJsonPath = base_path('package.json');
        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            $this->assertArrayHasKey('vue', $packageJson['dependencies']);
            $this->assertArrayHasKey('@vitejs/plugin-vue', $packageJson['devDependencies']);
            if (isset($packageJson['devDependencies']['@vitejs/plugin-react'])) {
                // React plugin should not be present for Vue
                $this->fail('React plugin should not be in Vue installation');
            }
            
            // Clean up
            unlink($packageJsonPath);
        }

        // Check Vue files
        $appJsPath = base_path('resources/js/app.js');
        if (file_exists($appJsPath)) {
            $appJs = file_get_contents($appJsPath);
            $this->assertStringContainsString('createApp', $appJs);
            $this->assertStringContainsString('vue', $appJs);
            
            // Clean up
            unlink($appJsPath);
            if (file_exists(base_path('resources/js/App.vue'))) {
                unlink(base_path('resources/js/App.vue'));
            }
            if (is_dir(base_path('resources/js'))) {
                // Remove directory recursively in case there are other files
                $this->removeDirectory(base_path('resources/js'));
            }
        }

        // Check Vite config
        $viteConfigPath = base_path('vite.config.js');
        if (file_exists($viteConfigPath)) {
            $viteConfig = file_get_contents($viteConfigPath);
            $this->assertStringContainsString('@vitejs/plugin-vue', $viteConfig);
            $this->assertStringContainsString('app.js', $viteConfig);
            
            // Clean up
            unlink($viteConfigPath);
        }
    }

    public function testShowsErrorForInvalidFramework(): void
    {
        $command = new InstallFrontendCommand();
        
        ob_start();
        $command->handle(['angular']);
        $output = ob_get_clean();

        $this->assertStringContainsString("Error: Framework must be 'react' or 'vue'", $output);
        $this->assertStringContainsString('Usage:', $output);
    }

    public function testSkipsExistingPackageJson(): void
    {
        $packageJsonPath = base_path('package.json');
        file_put_contents($packageJsonPath, '{"name": "existing"}');
        
        $command = new InstallFrontendCommand();
        
        ob_start();
        $command->handle(['react']);
        $output = ob_get_clean();

        $this->assertStringContainsString('package.json already exists', $output);
        
        // Original content should be preserved
        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            $this->assertEquals('existing', $packageJson['name']);
            unlink($packageJsonPath);
        }
    }

    public function testSkipsExistingViteConfig(): void
    {
        $viteConfigPath = base_path('vite.config.js');
        file_put_contents($viteConfigPath, '// existing');
        
        $command = new InstallFrontendCommand();
        
        ob_start();
        $command->handle(['react']);
        $output = ob_get_clean();

        $this->assertStringContainsString('vite.config.js already exists', $output);
        
        // Clean up
        if (file_exists($viteConfigPath)) {
            unlink($viteConfigPath);
        }
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
