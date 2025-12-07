<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Console\MakeMigrationCommand;
use Tests\TestCase;

class MakeMigrationCommandTest extends TestCase
{
    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/baremetal_migration_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        chdir(sys_get_temp_dir());
        parent::tearDown();
    }

    public function testCanCreateMigration(): void
    {
        $command = new MakeMigrationCommand();
        
        ob_start();
        $command->handle(['create_users_table']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Migration created', $output);
        
        // Check migrations directory was created
        $this->assertDirectoryExists($this->testDir . '/database/migrations');
        
        // Find the migration file (timestamped)
        $files = glob($this->testDir . '/database/migrations/*_create_users_table.php');
        $this->assertNotEmpty($files, 'Migration file should exist');
        
        $migrationFile = $files[0];
        $this->assertFileExists($migrationFile);
        
        $content = file_get_contents($migrationFile);
        $this->assertStringContainsString('create_users_table', $content);
        $this->assertStringContainsString('extends Migration', $content);
        $this->assertStringContainsString('public function up', $content);
        $this->assertStringContainsString('public function down', $content);
    }

    public function testShowsUsageWhenNoNameProvided(): void
    {
        $command = new MakeMigrationCommand();
        
        ob_start();
        $command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('make:migration', $output);
        $this->assertStringContainsString('Example:', $output);
    }

    public function testSkipsExistingMigration(): void
    {
        mkdir($this->testDir . '/database/migrations', 0755, true);
        $existingFile = $this->testDir . '/database/migrations/20241204120000_create_users_table.php';
        file_put_contents($existingFile, '<?php // existing');
        
        $command = new MakeMigrationCommand();
        
        ob_start();
        $command->handle(['create_users_table']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Migration already exists', $output);
        
        // Original content should be preserved
        $content = file_get_contents($existingFile);
        $this->assertStringContainsString('existing', $content);
    }

    public function testMigrationHasTimestamp(): void
    {
        $command = new MakeMigrationCommand();
        $command->handle(['test_migration']);
        
        $files = glob($this->testDir . '/database/migrations/*_test_migration.php');
        $this->assertNotEmpty($files);
        
        $filename = basename($files[0]);
        // Should start with timestamp (YYYYMMDDHHMMSS)
        $this->assertMatchesRegularExpression('/^\d{14}_test_migration\.php$/', $filename);
    }

    public function testCreatesMigrationsDirectory(): void
    {
        $command = new MakeMigrationCommand();
        $command->handle(['test_migration']);
        
        $this->assertDirectoryExists($this->testDir . '/database');
        $this->assertDirectoryExists($this->testDir . '/database/migrations');
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
