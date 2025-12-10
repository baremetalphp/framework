<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use BareMetalPHP\Database\Builder;
use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\Model;
use BareMetalPHP\Support\Collection;
use Tests\TestCase;

class BuilderAdvancedTest extends TestCase
{
    protected bool $needsDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();

        $this->createTable('users', <<<SQL
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                age INTEGER,
                status TEXT
            )
        SQL);

        $this->createTable('posts', <<<SQL
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL
            )
        SQL);
    }

    public function testWhereIn(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $pdo->exec("INSERT INTO users (name, email, age) VALUES ('Alice', 'alice@test.com', 25)");
        $pdo->exec("INSERT INTO users (name, email, age) VALUES ('Bob', 'bob@test.com', 30)");
        $pdo->exec("INSERT INTO users (name, email, age) VALUES ('Charlie', 'charlie@test.com', 35)");

        $builder = new Builder($pdo, 'users', null, $connection);
        $results = $builder->whereIn('age', [25, 35])->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
        $this->assertEquals('Alice', $results->first()['name']);
        $this->assertEquals('Charlie', $results->toArray()[1]['name']);
    }

    public function testWhereNotIn(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $pdo->exec("INSERT INTO users (name, email, age) VALUES ('Alice', 'alice@test.com', 25)");
        $pdo->exec("INSERT INTO users (name, email, age) VALUES ('Bob', 'bob@test.com', 30)");
        $pdo->exec("INSERT INTO users (name, email, age) VALUES ('Charlie', 'charlie@test.com', 35)");

        $builder = new Builder($pdo, 'users', null, $connection);
        $results = $builder->whereNotIn('age', [25, 35])->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertEquals('Bob', $results->first()['name']);
    }

    public function testWithEagerLoading(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $user = TestUserForEager::create(['name' => 'John', 'email' => 'john@test.com']);
        TestPostForEager::create(['user_id' => $user->id, 'title' => 'Post 1']);
        TestPostForEager::create(['user_id' => $user->id, 'title' => 'Post 2']);

        $builder = new Builder($pdo, 'users', TestUserForEager::class, $connection);
        $results = $builder->with('posts')->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        
        $user = $results->first();
        $this->assertInstanceOf(TestUserForEager::class, $user);
    }

    public function testWithMultipleRelations(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $user = TestUserForEager::create(['name' => 'John', 'email' => 'john@test.com']);

        $builder = new Builder($pdo, 'users', TestUserForEager::class, $connection);
        $results = $builder->with('posts', 'profile')->get();

        $this->assertInstanceOf(Collection::class, $results);
        
        // Verify eagerLoad was set
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('eagerLoad');
        $property->setAccessible(true);
        $eagerLoad = $property->getValue($builder);
        $this->assertArrayHasKey('posts', $eagerLoad);
        $this->assertArrayHasKey('profile', $eagerLoad);
    }

    public function testWithArraySyntax(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $user = TestUserForEager::create(['name' => 'John', 'email' => 'john@test.com']);

        $builder = new Builder($pdo, 'users', TestUserForEager::class, $connection);
        $results = $builder->with(['posts', 'profile'])->get();

        $this->assertInstanceOf(Collection::class, $results);
        
        // Verify eagerLoad was set
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('eagerLoad');
        $property->setAccessible(true);
        $eagerLoad = $property->getValue($builder);
        $this->assertArrayHasKey('posts', $eagerLoad);
        $this->assertArrayHasKey('profile', $eagerLoad);
    }

    public function testCount(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $pdo->exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@test.com')");
        $pdo->exec("INSERT INTO users (name, email) VALUES ('Bob', 'bob@test.com')");
        $pdo->exec("INSERT INTO users (name, email) VALUES ('Charlie', 'charlie@test.com')");

        $builder = new Builder($pdo, 'users', null, $connection);
        $count = $builder->count();

        $this->assertEquals(3, $count);
    }

    public function testCountWithWhere(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $pdo->exec("INSERT INTO users (name, email, status) VALUES ('Alice', 'alice@test.com', 'active')");
        $pdo->exec("INSERT INTO users (name, email, status) VALUES ('Bob', 'bob@test.com', 'inactive')");
        $pdo->exec("INSERT INTO users (name, email, status) VALUES ('Charlie', 'charlie@test.com', 'active')");

        $builder = new Builder($pdo, 'users', null, $connection);
        $count = $builder->where('status', 'active')->count();

        $this->assertEquals(2, $count);
    }

    public function testFirst(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $pdo->exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@test.com')");
        $pdo->exec("INSERT INTO users (name, email) VALUES ('Bob', 'bob@test.com')");

        $builder = new Builder($pdo, 'users', null, $connection);
        $first = $builder->first();

        $this->assertIsArray($first);
        $this->assertEquals('Alice', $first['name']);
    }

    public function testFirstWithModel(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $user = TestUserForEager::create(['name' => 'John', 'email' => 'john@test.com']);

        $builder = new Builder($pdo, 'users', TestUserForEager::class, $connection);
        $first = $builder->first();

        $this->assertInstanceOf(TestUserForEager::class, $first);
        $this->assertEquals('John', $first->name);
    }

    public function testFirstReturnsNullWhenNoResults(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');

        $builder = new Builder($pdo, 'users', null, $connection);
        $first = $builder->where('id', '=', 999)->first();

        $this->assertNull($first);
    }

    public function testToSql(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');

        $builder = new Builder($pdo, 'users', null, $connection);
        $sql = $builder->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->limit(10)
            ->toSql();

        $this->assertIsString($sql);
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('users', $sql);
    }

    public function testOrWhere(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $pdo->exec("INSERT INTO users (name, email, status) VALUES ('Alice', 'alice@test.com', 'active')");
        $pdo->exec("INSERT INTO users (name, email, status) VALUES ('Bob', 'bob@test.com', 'inactive')");
        $pdo->exec("INSERT INTO users (name, email, status) VALUES ('Charlie', 'charlie@test.com', 'pending')");

        $builder = new Builder($pdo, 'users', null, $connection);
        $results = $builder->where('status', 'active')
            ->orWhere('status', 'pending')
            ->get();

        $this->assertCount(2, $results);
    }

    public function testWhereShorthand(): void
    {
        $pdo = $this->getPdo();
        $connection = new Connection('sqlite::memory:');
        
        $pdo->exec("INSERT INTO users (name, email, status) VALUES ('Alice', 'alice@test.com', 'active')");
        $pdo->exec("INSERT INTO users (name, email, status) VALUES ('Bob', 'bob@test.com', 'inactive')");

        $builder = new Builder($pdo, 'users', null, $connection);
        $results = $builder->where('status', 'active')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Alice', $results->first()['name']);
    }
}

class TestUserForEager extends Model
{
    protected static string $table = 'users';
    protected bool $timestamps = false;

    public function posts()
    {
        return $this->hasMany(TestPostForEager::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(TestPostForEager::class, 'user_id');
    }
}

class TestPostForEager extends Model
{
    protected static string $table = 'posts';
    protected bool $timestamps = false;
}

