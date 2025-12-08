<?php

declare(strict_types=1);

namespace Tests\Feature;

use BareMetalPHP\Console\MigrateCommand;
use BareMetalPHP\Console\MigrateRollbackCommand;
use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\ConnectionManager;
use Tests\TestCase;

class MigrateRollbackCommandTest extends TestCase
{
    protected string $testDir;
    protected Connection $connection;
    protected MigrateCommand $migrateCommand;
    protected MigrateRollbackCommand $rollbackCommand;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testDir = sys_get_temp_dir() . '/baremetal_rollback_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
        mkdir($this->testDir . '/database/migrations', 0755, true);
        
        chdir($this->testDir);
        
        // Create in-memory database connection
        $this->connection = new Connection('sqlite::memory:');
        
        // Create ConnectionManager mock
        $manager = $this->createMock(ConnectionManager::class);
        $manager->method('connection')->willReturn($this->connection);
        
        $this->migrateCommand = new MigrateCommand($manager);
        $this->rollbackCommand = new MigrateRollbackCommand($manager);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        chdir(sys_get_temp_dir());
        parent::tearDown();
    }

    public function testCanRollbackMigrations(): void
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
        $this->createTable($connection, 'test_rollback', function ($table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(Connection $connection): void
    {
        $this->dropTable($connection, 'test_rollback');
    }
};
PHP;
        
        file_put_contents(
            $this->testDir . '/database/migrations/20241204120000_create_test_rollback_table.php',
            $migrationContent
        );

        // Run migration first
        ob_start();
        $this->migrateCommand->handle([]);
        ob_get_clean();

        // Verify table exists
        $pdo = $this->connection->pdo();
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_rollback'")->fetchAll();
        $this->assertNotEmpty($tables);

        // Ensure connection is clean - commit any pending transactions and set busy timeout
        $pdo->exec("PRAGMA busy_timeout = 30000");
        // Force any pending operations to complete
        $pdo->query("SELECT 1")->fetch();

        // Rollback
        ob_start();
        $this->rollbackCommand->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Rolling back:', $output);
        $this->assertStringContainsString('Rollback of batch', $output);

        // Verify table was dropped
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_rollback'")->fetchAll();
        $this->assertEmpty($tables);

        // Verify migration record was removed
        $migrations = $pdo->query("SELECT * FROM migrations")->fetchAll();
        $this->assertEmpty($migrations);
    }

    public function testHandlesNoMigrationsToRollback(): void
    {
        ob_start();
        $this->rollbackCommand->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Nothing to rollback', $output);
    }

    public function testRollsBackOnlyLatestBatch(): void
    {
        // Create two migrations
        $migration1 = <<<'PHP'
<?php

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\Migration;

return new class extends Migration
{
    public function up(Connection $connection): void
    {
        $this->createTable($connection, 'batch1', function ($table) {
            $table->id();
        });
    }

    public function down(Connection $connection): void
    {
        $this->dropTable($connection, 'batch1');
    }
};
PHP;

        $migration2 = <<<'PHP'
<?php

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\Migration;

return new class extends Migration
{
    public function up(Connection $connection): void
    {
        $this->createTable($connection, 'batch2', function ($table) {
            $table->id();
        });
    }

    public function down(Connection $connection): void
    {
        $this->dropTable($connection, 'batch2');
    }
};
PHP;
        
        file_put_contents($this->testDir . '/database/migrations/20241204120000_batch1.php', $migration1);
        
        // Run first migration
        ob_start();
        $this->migrateCommand->handle([]);
        ob_get_clean();

        file_put_contents($this->testDir . '/database/migrations/20241204120001_batch2.php', $migration2);
        
        // Run second migration (new batch)
        ob_start();
        $this->migrateCommand->handle([]);
        ob_get_clean();

        // Ensure connection is clean - commit any pending transactions and set busy timeout
        $pdo = $this->connection->pdo();
        $pdo->exec("PRAGMA busy_timeout = 30000");
        // Force any pending operations to complete
        $pdo->query("SELECT 1")->fetch();

        // Rollback - should only rollback batch2
        ob_start();
        $this->rollbackCommand->handle([]);
        ob_get_clean();

        $pdo = $this->connection->pdo();
        
        // batch2 should be gone
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='batch2'")->fetchAll();
        $this->assertEmpty($tables);
        
        // batch1 should still exist
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='batch1'")->fetchAll();
        $this->assertNotEmpty($tables);
    }

    public function testSkipsMissingMigrationFiles(): void
    {
        // Create migration and run it
        $migrationContent = <<<'PHP'
<?php

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\Migration;

return new class extends Migration
{
    public function up(Connection $connection): void
    {
        $this->createTable($connection, 'missing_file', function ($table) {
            $table->id();
        });
    }

    public function down(Connection $connection): void
    {
        $this->dropTable($connection, 'missing_file');
    }
};
PHP;
        
        $migrationFile = $this->testDir . '/database/migrations/20241204120000_missing_file.php';
        file_put_contents($migrationFile, $migrationContent);

        ob_start();
        $this->migrateCommand->handle([]);
        ob_get_clean();

        // Delete the migration file
        unlink($migrationFile);

        // Try to rollback
        ob_start();
        $this->rollbackCommand->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Skipping missing migration', $output);
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
