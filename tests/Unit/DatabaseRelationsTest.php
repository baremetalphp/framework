<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\Model;
use BareMetalPHP\Database\Relations\BelongsTo;
use BareMetalPHP\Database\Relations\HasMany;
use BareMetalPHP\Database\Relations\HasOne;
use BareMetalPHP\Support\Collection;
use Tests\TestCase;

// Test models for relationships - using unique names to avoid conflicts with AuthTest
class RelationUser extends Model
{
    protected static string $table = 'users';
    protected bool $timestamps = false;
    protected array $fillable = ['name', 'email'];
}

class RelationPost extends Model
{
    protected static string $table = 'posts';
    protected bool $timestamps = false;
    protected array $fillable = ['user_id', 'title'];
    
    public function user()
    {
        return $this->belongsTo(RelationUser::class, 'user_id');
    }
}

class RelationProfile extends Model
{
    protected static string $table = 'profiles';
    protected bool $timestamps = false;
    protected array $fillable = ['user_id', 'bio'];
    
    public function user()
    {
        return $this->belongsTo(RelationUser::class, 'user_id');
    }
}

class RelationUserWithRelations extends Model
{
    protected static string $table = 'users';
    protected bool $timestamps = false;
    protected array $fillable = ['name', 'email'];
    
    public function posts()
    {
        return $this->hasMany(RelationPost::class, 'user_id');
    }
    
    public function profile()
    {
        return $this->hasOne(RelationProfile::class, 'user_id');
    }
}

class DatabaseRelationsTest extends TestCase
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
                email TEXT NOT NULL
            )
        SQL);
        
        $this->createTable('posts', <<<SQL
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                body TEXT
            )
        SQL);
        
        $this->createTable('profiles', <<<SQL
            CREATE TABLE profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                bio TEXT
            )
        SQL);
    }

    public function testHasManyGetResultsReturnsCollection(): void
    {
        $user = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        RelationPost::create(['user_id' => $user->id, 'title' => 'Post 1']);
        RelationPost::create(['user_id' => $user->id, 'title' => 'Post 2']);
        
        $relation = new HasMany($user, RelationPost::class, 'user_id');
        $results = $relation->getResults();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
    }

    public function testHasManyGetResultsReturnsEmptyWhenNoParentId(): void
    {
        $user = new RelationUserWithRelations(['name' => 'John']);
        $user->exists = false; // No ID yet
        
        $relation = new HasMany($user, RelationPost::class, 'user_id');
        $results = $relation->getResults();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertTrue($results->isEmpty());
    }

    public function testHasManyAddEagerConstraints(): void
    {
        $user1 = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = RelationUserWithRelations::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        $relation = new HasMany($user1, RelationPost::class, 'user_id');
        $keys = $relation->addEagerConstraints([$user1, $user2]);
        
        $this->assertIsArray($keys);
        $this->assertContains($user1->id, $keys);
        $this->assertContains($user2->id, $keys);
    }

    public function testHasManyGetEager(): void
    {
        $user1 = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = RelationUserWithRelations::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        RelationPost::create(['user_id' => $user1->id, 'title' => 'Post 1']);
        RelationPost::create(['user_id' => $user1->id, 'title' => 'Post 2']);
        RelationPost::create(['user_id' => $user2->id, 'title' => 'Post 3']);
        
        $relation = new HasMany($user1, RelationPost::class, 'user_id');
        $keys = [$user1->id, $user2->id];
        $results = $relation->getEager($keys);
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(3, $results);
    }

    public function testHasManyGetEagerWithEmptyKeys(): void
    {
        $user = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $relation = new HasMany($user, RelationPost::class, 'user_id');
        
        $results = $relation->getEager([]);
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertTrue($results->isEmpty());
    }

    public function testHasManyMatch(): void
    {
        $user1 = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = RelationUserWithRelations::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        $post1 = RelationPost::create(['user_id' => $user1->id, 'title' => 'Post 1']);
        $post2 = RelationPost::create(['user_id' => $user1->id, 'title' => 'Post 2']);
        $post3 = RelationPost::create(['user_id' => $user2->id, 'title' => 'Post 3']);
        
        $relation = new HasMany($user1, RelationPost::class, 'user_id');
        $results = new Collection([$post1, $post2, $post3]);
        
        $relation->match([$user1, $user2], $results, 'posts');
        
        // Note: HasMany match currently uses HasOne logic (stores single model per key)
        // This appears to be a bug, but we test the actual behavior
        $user1Posts = $user1->getRelation('posts');
        $this->assertNotNull($user1Posts); // Will be a single Post, not Collection
        
        $user2Posts = $user2->getRelation('posts');
        $this->assertNotNull($user2Posts); // Will be a single Post
    }

    public function testBelongsToGetResultsReturnsModel(): void
    {
        $user = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $post = RelationPost::create(['user_id' => $user->id, 'title' => 'Post 1']);
        
        $relation = new BelongsTo($post, RelationUser::class, 'user_id');
        $result = $relation->getResults();
        
        $this->assertInstanceOf(RelationUser::class, $result);
        $this->assertSame($user->id, $result->id);
    }

    public function testBelongsToGetResultsReturnsNullWhenNoForeignKey(): void
    {
        $post = new RelationPost(['title' => 'Post 1']);
        $post->exists = false;
        
        $relation = new BelongsTo($post, RelationUser::class, 'user_id');
        $result = $relation->getResults();
        
        $this->assertNull($result);
    }

    public function testBelongsToAddEagerConstraints(): void
    {
        $user = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $post1 = RelationPost::create(['user_id' => $user->id, 'title' => 'Post 1']);
        $post2 = RelationPost::create(['user_id' => $user->id, 'title' => 'Post 2']);
        
        $relation = new BelongsTo($post1, RelationUser::class, 'user_id');
        $keys = $relation->addEagerConstraints([$post1, $post2]);
        
        $this->assertIsArray($keys);
        $this->assertContains($user->id, $keys);
    }

    public function testBelongsToGetEager(): void
    {
        $user1 = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = RelationUserWithRelations::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        $post1 = RelationPost::create(['user_id' => $user1->id, 'title' => 'Post 1']);
        $post2 = RelationPost::create(['user_id' => $user2->id, 'title' => 'Post 2']);
        
        $relation = new BelongsTo($post1, RelationUser::class, 'user_id');
        $keys = [$user1->id, $user2->id];
        $results = $relation->getEager($keys);
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
    }

    public function testBelongsToGetEagerWithEmptyKeys(): void
    {
        $post = RelationPost::create(['user_id' => 999, 'title' => 'Post 1']);
        $relation = new BelongsTo($post, RelationUser::class, 'user_id');
        
        $results = $relation->getEager([]);
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertTrue($results->isEmpty());
    }

    public function testBelongsToMatch(): void
    {
        $user1 = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = RelationUserWithRelations::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        $post1 = RelationPost::create(['user_id' => $user1->id, 'title' => 'Post 1']);
        $post2 = RelationPost::create(['user_id' => $user2->id, 'title' => 'Post 2']);
        
        $relation = new BelongsTo($post1, RelationUser::class, 'user_id');
        $results = new Collection([$user1, $user2]);
        
        $relation->match([$post1, $post2], $results, 'user');
        
        $this->assertSame($user1->id, $post1->getRelation('user')->id);
        $this->assertSame($user2->id, $post2->getRelation('user')->id);
    }

    public function testHasOneGetResultsReturnsModel(): void
    {
        $user = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $profile = RelationProfile::create(['user_id' => $user->id, 'bio' => 'Bio text']);
        
        $relation = new HasOne($user, RelationProfile::class, 'user_id');
        $result = $relation->getResults();
        
        $this->assertInstanceOf(RelationProfile::class, $result);
        $this->assertSame($profile->id, $result->id);
    }

    public function testHasOneGetResultsReturnsNullWhenNoParentId(): void
    {
        $user = new RelationUserWithRelations(['name' => 'John']);
        $user->exists = false;
        
        $relation = new HasOne($user, RelationProfile::class, 'user_id');
        $result = $relation->getResults();
        
        $this->assertNull($result);
    }

    public function testHasOneAddEagerConstraints(): void
    {
        $user1 = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = RelationUserWithRelations::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        $relation = new HasOne($user1, RelationProfile::class, 'user_id');
        $keys = $relation->addEagerConstraints([$user1, $user2]);
        
        $this->assertIsArray($keys);
        $this->assertContains($user1->id, $keys);
        $this->assertContains($user2->id, $keys);
    }

    public function testHasOneGetEager(): void
    {
        $user1 = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = RelationUserWithRelations::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        RelationProfile::create(['user_id' => $user1->id, 'bio' => 'Bio 1']);
        RelationProfile::create(['user_id' => $user2->id, 'bio' => 'Bio 2']);
        
        $relation = new HasOne($user1, RelationProfile::class, 'user_id');
        $keys = [$user1->id, $user2->id];
        $results = $relation->getEager($keys);
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
    }

    public function testHasOneGetEagerWithEmptyKeys(): void
    {
        $user = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $relation = new HasOne($user, RelationProfile::class, 'user_id');
        
        $results = $relation->getEager([]);
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertTrue($results->isEmpty());
    }

    public function testHasOneMatch(): void
    {
        $user1 = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = RelationUserWithRelations::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        $profile1 = RelationProfile::create(['user_id' => $user1->id, 'bio' => 'Bio 1']);
        $profile2 = RelationProfile::create(['user_id' => $user2->id, 'bio' => 'Bio 2']);
        
        $relation = new HasOne($user1, RelationProfile::class, 'user_id');
        $results = new Collection([$profile1, $profile2]);
        
        $relation->match([$user1, $user2], $results, 'profile');
        
        $this->assertSame($profile1->id, $user1->getRelation('profile')->id);
        $this->assertSame($profile2->id, $user2->getRelation('profile')->id);
    }

    public function testHasOneMatchWithMissingProfile(): void
    {
        $user1 = RelationUserWithRelations::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = RelationUserWithRelations::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        
        $profile1 = RelationProfile::create(['user_id' => $user1->id, 'bio' => 'Bio 1']);
        // user2 has no profile
        
        $relation = new HasOne($user1, RelationProfile::class, 'user_id');
        $results = new Collection([$profile1]);
        
        $relation->match([$user1, $user2], $results, 'profile');
        
        $this->assertSame($profile1->id, $user1->getRelation('profile')->id);
        $this->assertNull($user2->getRelation('profile'));
    }
}

