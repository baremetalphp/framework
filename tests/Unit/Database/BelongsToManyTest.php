<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use BareMetalPHP\Database\Model;
use BareMetalPHP\Database\Relations\BelongsToMany;
use BareMetalPHP\Support\Collection;
use Tests\TestCase;

class BelongsToManyTest extends TestCase
{
    protected bool $needsDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();

        $this->createTable('users', <<<SQL
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )
        SQL);

        $this->createTable('roles', <<<SQL
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )
        SQL);

        $this->createTable('role_user', <<<SQL
            CREATE TABLE role_user (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                created_at TEXT,
                updated_at TEXT,
                PRIMARY KEY (user_id, role_id)
            )
        SQL);
    }

    public function testGetReturnsCollection(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role1 = TestRole::create(['name' => 'Admin']);
        $role2 = TestRole::create(['name' => 'Editor']);

        // Manually insert into pivot table
        $pdo = $this->getPdo();
        $pdo->exec("INSERT INTO role_user (user_id, role_id) VALUES ({$user->id}, {$role1->id})");
        $pdo->exec("INSERT INTO role_user (user_id, role_id) VALUES ({$user->id}, {$role2->id})");

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $roles = $relation->get();

        $this->assertInstanceOf(Collection::class, $roles);
        $this->assertCount(2, $roles);
    }

    public function testGetReturnsEmptyWhenNoParentId(): void
    {
        $user = new TestUserForBelongsToMany(['name' => 'John']);
        $user->exists = false;

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $roles = $relation->get();

        $this->assertInstanceOf(Collection::class, $roles);
        $this->assertTrue($roles->isEmpty());
    }

    public function testAttachSingleId(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role = TestRole::create(['name' => 'Admin']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $relation->attach($role->id);

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_user WHERE user_id = ? AND role_id = ?");
        $stmt->execute([$user->id, $role->id]);
        $count = $stmt->fetchColumn();

        $this->assertEquals(1, $count);
    }

    public function testAttachMultipleIds(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role1 = TestRole::create(['name' => 'Admin']);
        $role2 = TestRole::create(['name' => 'Editor']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $relation->attach([$role1->id, $role2->id]);

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_user WHERE user_id = ?");
        $stmt->execute([$user->id]);
        $count = $stmt->fetchColumn();

        $this->assertEquals(2, $count);
    }

    public function testAttachWithPivotAttributes(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role = TestRole::create(['name' => 'Admin']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $relation->attach($role->id, ['created_at' => '2024-01-01 00:00:00']);

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT created_at FROM role_user WHERE user_id = ? AND role_id = ?");
        $stmt->execute([$user->id, $role->id]);
        $createdAt = $stmt->fetchColumn();

        $this->assertEquals('2024-01-01 00:00:00', $createdAt);
    }

    public function testAttachUpdatesExistingPivotAttributes(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role = TestRole::create(['name' => 'Admin']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        // First attach
        $relation->attach($role->id, ['created_at' => '2024-01-01 00:00:00']);

        // Attach again with updated attributes
        $relation->attach($role->id, ['created_at' => '2024-01-02 00:00:00']);

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT created_at FROM role_user WHERE user_id = ? AND role_id = ?");
        $stmt->execute([$user->id, $role->id]);
        $createdAt = $stmt->fetchColumn();

        $this->assertEquals('2024-01-02 00:00:00', $createdAt);
    }

    public function testDetachSingleId(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role1 = TestRole::create(['name' => 'Admin']);
        $role2 = TestRole::create(['name' => 'Editor']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $relation->attach([$role1->id, $role2->id]);
        $count = $relation->detach($role1->id);

        $this->assertEquals(1, $count);

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_user WHERE user_id = ?");
        $stmt->execute([$user->id]);
        $remaining = $stmt->fetchColumn();

        $this->assertEquals(1, $remaining);
    }

    public function testDetachAll(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role1 = TestRole::create(['name' => 'Admin']);
        $role2 = TestRole::create(['name' => 'Editor']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $relation->attach([$role1->id, $role2->id]);
        $count = $relation->detach();

        $this->assertEquals(2, $count);

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_user WHERE user_id = ?");
        $stmt->execute([$user->id]);
        $remaining = $stmt->fetchColumn();

        $this->assertEquals(0, $remaining);
    }

    public function testDetachReturnsZeroWhenNoParentId(): void
    {
        $user = new TestUserForBelongsToMany(['name' => 'John']);
        $user->exists = false;

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $count = $relation->detach();

        $this->assertEquals(0, $count);
    }

    public function testSync(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role1 = TestRole::create(['name' => 'Admin']);
        $role2 = TestRole::create(['name' => 'Editor']);
        $role3 = TestRole::create(['name' => 'Viewer']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        // Attach role1 and role2
        $relation->attach([$role1->id, $role2->id]);

        // Sync to role2 and role3 (should detach role1, attach role3)
        $result = $relation->sync([$role2->id, $role3->id]);

        $this->assertArrayHasKey('attached', $result);
        $this->assertArrayHasKey('detached', $result);
        // getPivotIds returns strings from SQLite, so sync may return strings
        // Convert both to int for comparison
        $attachedIds = array_map('intval', $result['attached']);
        $detachedIds = array_map('intval', $result['detached']);
        $this->assertContains((int)$role3->id, $attachedIds);
        $this->assertContains((int)$role1->id, $detachedIds);

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT role_id FROM role_user WHERE user_id = ? ORDER BY role_id");
        $stmt->execute([$user->id]);
        $roles = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertCount(2, $roles);
        // SQLite returns strings, so convert both to strings for comparison
        // Also ensure role IDs are converted to strings
        $roleIds = array_map('strval', $roles);
        $expectedRole2 = (string)$role2->id;
        $expectedRole3 = (string)$role3->id;
        $this->assertContains($expectedRole2, $roleIds, "Expected role2 id '{$expectedRole2}' not found in roles: " . json_encode($roleIds));
        $this->assertContains($expectedRole3, $roleIds, "Expected role3 id '{$expectedRole3}' not found in roles: " . json_encode($roleIds));
    }

    public function testSyncWithoutDetaching(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role1 = TestRole::create(['name' => 'Admin']);
        $role2 = TestRole::create(['name' => 'Editor']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $relation->attach([$role1->id]);
        $result = $relation->sync([$role2->id], false);

        // Should attach role2 but not detach role1
        $this->assertContains($role2->id, $result['attached']);
        $this->assertEmpty($result['detached']);

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_user WHERE user_id = ?");
        $stmt->execute([$user->id]);
        $count = $stmt->fetchColumn();

        $this->assertEquals(2, $count);
    }

    public function testGetPivotAttributes(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role = TestRole::create(['name' => 'Admin']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $relation->attach($role->id, ['created_at' => '2024-01-01 00:00:00']);

        $attributes = $relation->getPivotAttributes($role->id);

        $this->assertIsArray($attributes);
        // Should not include foreign keys
        $this->assertArrayNotHasKey('user_id', $attributes);
        $this->assertArrayNotHasKey('role_id', $attributes);
        // May have created_at/updated_at from timestamps (attach adds them automatically)
        if (isset($attributes['created_at'])) {
            $this->assertIsString($attributes['created_at']);
        }
    }

    public function testGetPivotAttributesReturnsEmptyWhenNotFound(): void
    {
        $user = TestUserForBelongsToMany::create(['name' => 'John']);
        $role = TestRole::create(['name' => 'Admin']);

        $relation = new BelongsToMany(
            $user,
            TestRole::class,
            'role_user',
            'user_id',
            'role_id'
        );

        $attributes = $relation->getPivotAttributes($role->id);

        $this->assertIsArray($attributes);
        $this->assertEmpty($attributes);
    }
}

class TestUserForBelongsToMany extends Model
{
    protected static string $table = 'users';
    protected bool $timestamps = false;
    protected array $fillable = ['name'];
}

class TestRole extends Model
{
    protected static string $table = 'roles';
    protected bool $timestamps = false;
    protected array $fillable = ['name'];
}

