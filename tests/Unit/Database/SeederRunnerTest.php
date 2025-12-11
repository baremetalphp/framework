<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use BareMetalPHP\Database\Seeder\SeederRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeederRunnerTest extends TestCase
{
    private string $seederDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seederDir = sys_get_temp_dir() . '/baremetalphp-seeders-' . uniqid('', true);

        if (! is_dir($this->seederDir)) {
            mkdir($this->seederDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->seederDir)) {
            foreach (glob($this->seederDir . '/*.php') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($this->seederDir);
        }

        parent::tearDown();
    }

    public function testSeederRunsTheGivenSeederClass(): void
    {
        // Arrange: fake DatabaseSeeder that tracks execution.
        $className = 'TestSeeder' . uniqid();
        $this->writeSeederFile($className . '.php', <<<PHP
<?php

use BareMetalPHP\Database\Seeder\Seeder as BaseSeeder;

class {$className} extends BaseSeeder
{
    public static bool \$ran = false;

    public function run(): void
    {
        self::\$ran = true;
    }
}
PHP
);

        $runner = new SeederRunner($this->seederDir);

        // Act
        $runner->run($className);

        // Assert
        $this->assertTrue($className::$ran);
    }

    public function testItCanChainOtherSeedersViaCall(): void
    {
        // Use unique class names to avoid redeclaration errors
        $postSeederClass = 'PostSeeder' . uniqid();
        $dbSeederClass = 'DatabaseSeeder' . uniqid();
        
        // Child seeder that will be called by DatabaseSeeder
        $this->writeSeederFile($postSeederClass . '.php', <<<PHP
<?php

use BareMetalPHP\Database\Seeder\Seeder as BaseSeeder;

class {$postSeederClass} extends BaseSeeder
{
    public static bool \$ran = false;

    public function run(): void
    {
        self::\$ran = true;
    }
}
PHP);

        // DatabaseSeeder that calls PostSeeder
        $seederDir = $this->seederDir;
        $this->writeSeederFile($dbSeederClass . '.php', <<<PHP
<?php

use BareMetalPHP\Database\Seeder\Seeder as BaseSeeder;

class {$dbSeederClass} extends BaseSeeder
{
    public static bool \$ran = false;

    public function run(): void
    {
        self::\$ran = true;

        \$this->call('{$postSeederClass}', '{$seederDir}');
    }
}
PHP
);

        $runner = new SeederRunner($this->seederDir);

        // Act
        $runner->run($dbSeederClass);

        // Assert
        $this->assertTrue($dbSeederClass::$ran, 'DatabaseSeeder::run should be executed');
        $this->assertTrue($postSeederClass::$ran, 'PostSeeder::run should be executed via call()');
    }

    public function testItThrowsWhenSeederFileDoesNotExist(): void
    {
        $runner = new SeederRunner($this->seederDir);

        $this->expectException(RuntimeException::class);

        $runner->run('MissingSeeder');
    }

    public function testItThrowsWhenClassDoesNotExtendSeeder(): void
    {
        // File exists, but class does not extend the base Seeder
        $className = 'InvalidSeeder' . uniqid();
        $this->writeSeederFile($className . '.php', <<<PHP
<?php

class {$className}
{
    public function run(): void
    {
        // noop
    }
}
PHP);

        $runner = new SeederRunner($this->seederDir);

        $this->expectException(RuntimeException::class);

        $runner->run($className);
    }

    /**
     * Helper to write a seeder file into the temporary seeder directory.
     */
    private function writeSeederFile(string $filename, string $contents): void
    {
        file_put_contents($this->seederDir . '/' . $filename, $contents);
    }
}
