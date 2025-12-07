<?php

declare(strict_types=1);

namespace Tests\Feature;

use BareMetalPHP\Console\MigrateCommand;
use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\ConnectionManager;
use BareMetalPHP\Database\Migration;
use Tests\TestCase;

class MigrateCommandTest extends TestCase
{
    protected string $testDir;
    protected Connection $connection;
    protected MigrateCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/baremetal_migrate_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
        mkdir($this->testDir . '/database/migrations', 0755, true);
        
        chdir($this->testDir);
        
        // Create in-memory database connection
        $this->connection = new Connection('sqlite::memory:');
        
        // Create ConnectionManager mock
        $manager = $this->createMock(ConnectionManager::class);
        $manager->method('connection')->willReturn($this->connection);
        
        $this->command = new MigrateCommand($manager);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        chdir(sys_get_temp_dir());
        parent::tearDown();
    }

    public function testCanRunMigrations(): void
    {
        // Create a test migration
        $migrationContent = <<<'PHP'
<?php

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\Migration;

return new class extends Migration
{
    public function up(Connection $connection): void
    {
        $this->createTable($connection, 'test_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        });
    }

    public function down(Connection $connection): void
    {
        $this->dropTable($connection, 'test_users');
    }
};
PHP;
        
        file_put_contents(
            $this->testDir . '/database/migrations/20241204120000_create_test_users_table.php',
            $migrationContent
        );

        ob_start();
        $this->command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Running migration:', $output);
        $this->assertStringContainsString('Migrations completed', $output);

        // Check that table was created
        $pdo = $this->connection->pdo();
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_users'")->fetchAll();
        $this->assertNotEmpty($tables);

        // Check that migration was recorded
        $migrations = $pdo->query("SELECT * FROM migrations WHERE migration LIKE '%create_test_users_table%'")->fetchAll();
        $this->assertNotEmpty($migrations);
    }

    public function testSkipsAlreadyRunMigrations(): void
    {
        // Create migration
        $migrationContent = <<<'PHP'
<?php

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\Migration;

return new class extends Migration
{
    public function up(Connection $connection): void
    {
        $this->createTable($connection, 'test_posts', function ($table) {
            $table->id();
            $table->string('title');
        });
    }

    public function down(Connection $connection): void
    {
        $this->dropTable($connection, 'test_posts');
    }
};
PHP;
        
        $migrationFile = $this->testDir . '/database/migrations/20241204120000_create_test_posts_table.php';
        file_put_contents($migrationFile, $migrationContent);

        // Run migration first time
        ob_start();
        $this->command->handle([]);
        ob_get_clean();

        // Run again - should skip
        ob_start();
        $this->command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Nothing to migrate', $output);
    }

    public function testHandlesNoMigrationsDirectory(): void
    {
        rmdir($this->testDir . '/database/migrations');
        rmdir($this->testDir . '/database');

        ob_start();
        $this->command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('No migrations directory found', $output);
    }

    public function testHandlesNoMigrationFiles(): void
    {
        ob_start();
        $this->command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('No migration files found', $output);
    }

    public function testCreatesMigrationsTable(): void
    {
        $this->command->handle([]);

        $pdo = $this->connection->pdo();
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'")->fetchAll();
        $this->assertNotEmpty($tables);
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
