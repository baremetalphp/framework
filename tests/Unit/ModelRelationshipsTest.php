<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Database\Model;
use BareMetalPHP\Support\Collection;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    protected bool $needsDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        // users table
        $this->createTable('users', <<<SQL
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )
        SQL);

        // profiles table (hasOne / belongsTo)
        $this->createTable('profiles', <<<SQL
            CREATE TABLE profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                bio TEXT
            )
        SQL);

        // posts table (hasMany / belongsTo)
        $this->createTable('posts', <<<SQL
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title TEXT NOT NULL,
                body TEXT
            )
        SQL);
    }

    public function test_has_many_returns_collection_of_related_models(): void
    {
        $user = UserWithRelations::create([
            'name'  => 'John',
            'email' => 'john@example.com',
        ]);

        PostForRelations::create([
            'user_id' => $user->id,
            'title'   => 'First Post',
            'body'    => 'Hello',
        ]);

        PostForRelations::create([
            'user_id' => $user->id,
            'title'   => 'Second Post',
            'body'    => 'World',
        ]);

        // Property access should trigger relationship loading
        $posts = $user->posts;

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(2, $posts);

        foreach ($posts as $post) {
            $this->assertInstanceOf(PostForRelations::class, $post);
            $this->assertSame($user->id, $post->user_id);
        }
    }

    public function test_has_many_returns_empty_collection_when_no_related_rows(): void
    {
        $user = UserWithRelations::create([
            'name'  => 'Lonely',
            'email' => 'lonely@example.com',
        ]);

        $posts = $user->posts;

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(0, $posts);
    }

    public function test_has_one_returns_single_related_model_via_method_and_property(): void
    {
        $user = UserWithRelations::create([
            'name'  => 'Jane',
            'email' => 'jane@example.com',
        ]);

        ProfileForRelations::create([
            'user_id' => $user->id,
            'bio'     => 'Developer',
        ]);

        // Direct method call (tests hasOne helper itself)
        $profileViaMethod = $user->profile();
        $this->assertInstanceOf(ProfileForRelations::class, $profileViaMethod);
        $this->assertSame($user->id, $profileViaMethod->user_id);

        // Property access (tests __get + relationship resolution)
        $profile = $user->profile;
        $this->assertInstanceOf(ProfileForRelations::class, $profile);
        $this->assertSame('Developer', $profile->bio);
    }

    public function test_has_one_method_returns_null_when_no_related_model(): void
    {
        $user = UserWithRelations::create([
            'name'  => 'NoProfile',
            'email' => 'noprof@example.com',
        ]);

        // We only assert method behavior here so you can change __get
        $this->assertNull($user->profile());
    }

    public function test_belongs_to_returns_parent_model(): void
    {
        $user = UserWithRelations::create([
            'name'  => 'Parent',
            'email' => 'parent@example.com',
        ]);

        $post = PostForRelations::create([
            'user_id' => $user->id,
            'title'   => 'Child Post',
            'body'    => 'Body',
        ]);

        // Direct method call
        $ownerViaMethod = $post->user();
        $this->assertInstanceOf(UserWithRelations::class, $ownerViaMethod);
        $this->assertSame($user->id, $ownerViaMethod->id);

        // Property access
        $owner = $post->user;
        $this->assertInstanceOf(UserWithRelations::class, $owner);
        $this->assertSame('Parent', $owner->name);
    }

    public function test_belongs_to_method_returns_null_when_foreign_key_is_null(): void
    {
        // Create a post with no valid user_id (simulating orphaned row)
        $post = new PostForRelations([
            'user_id' => null,
            'title'   => 'Orphaned',
            'body'    => 'No parent',
        ]);
        $post->save();

        $this->assertNull($post->user());
    }
}

/**
 * Simple test models used only for relationship tests
 */
class UserWithRelations extends Model
{
    protected static string $table = 'users';

    protected bool $timestamps = false;

    public function posts()
    {
        return $this->hasMany(PostForRelations::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(ProfileForRelations::class, 'user_id');
    }
}

class PostForRelations extends Model
{
    protected static string $table = 'posts';

    protected bool $timestamps = false;

    public function user()
    {
        return $this->belongsTo(UserWithRelations::class, 'user_id');
    }
}

class ProfileForRelations extends Model
{
    protected static string $table = 'profiles';

    protected bool $timestamps = false;
    public function user()
    {
        return $this->belongsTo(UserWithRelations::class, 'user_id');
    }
}
