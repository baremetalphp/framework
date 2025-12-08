<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Database\Model;
use BareMetalPHP\Database\Relations\MorphMany;
use BareMetalPHP\Database\Relations\MorphOne;
use BareMetalPHP\Database\Relations\MorphTo;
use BareMetalPHP\Support\Collection;
use Tests\TestCase;

class MorphRelationshipsTest extends TestCase
{
    protected bool $needsDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createTable('posts', <<<SQL
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT
            )
        SQL);
        
        $this->createTable('videos', <<<SQL
            CREATE TABLE videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                url TEXT
            )
        SQL);
        
        $this->createTable('comments', <<<SQL
            CREATE TABLE comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                body TEXT NOT NULL,
                commentable_type TEXT,
                commentable_id INTEGER,
                user_id INTEGER
            )
        SQL);
        
        $this->createTable('images', <<<SQL
            CREATE TABLE images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL,
                imageable_type TEXT,
                imageable_id INTEGER
            )
        SQL);
    }

    public function testMorphToReturnsNullWhenTypeAndIdAreNull(): void
    {
        $comment = new Comment();
        $comment->body = 'Test comment';
        $comment->commentable_type = null;
        $comment->commentable_id = null;
        $comment->save();
        
        $relation = new MorphTo($comment, 'commentable');
        $result = $relation->getResults();
        
        $this->assertNull($result);
    }

    public function testMorphToReturnsNullWhenClassDoesNotExist(): void
    {
        $comment = new Comment();
        $comment->body = 'Test comment';
        $comment->commentable_type = 'NonExistentClass';
        $comment->commentable_id = 1;
        $comment->save();
        
        $relation = new MorphTo($comment, 'commentable');
        $result = $relation->getResults();
        
        $this->assertNull($result);
    }

    public function testMorphToReturnsRelatedModel(): void
    {
        $post = new Post();
        $post->title = 'Test Post';
        $post->save();
        
        $comment = new Comment();
        $comment->body = 'Test comment';
        $comment->commentable_type = Post::class;
        $comment->commentable_id = $post->id;
        $comment->save();
        
        $relation = new MorphTo($comment, 'commentable');
        $result = $relation->getResults();
        
        $this->assertInstanceOf(Post::class, $result);
        $this->assertSame($post->id, $result->id);
    }

    public function testMorphManyReturnsCollectionOfRelatedModels(): void
    {
        $post = new Post();
        $post->title = 'Test Post';
        $post->save();
        
        $comment1 = new Comment();
        $comment1->body = 'Comment 1';
        $comment1->commentable_type = Post::class;
        $comment1->commentable_id = $post->id;
        $comment1->save();
        
        $comment2 = new Comment();
        $comment2->body = 'Comment 2';
        $comment2->commentable_type = Post::class;
        $comment2->commentable_id = $post->id;
        $comment2->save();
        
        $relation = new MorphMany($post, Comment::class, 'commentable_type', 'commentable_id');
        $results = $relation->getResults();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
    }

    public function testMorphManyReturnsEmptyCollectionWhenNoRelatedModels(): void
    {
        $post = new Post();
        $post->title = 'Test Post';
        $post->save();
        
        $relation = new MorphMany($post, Comment::class, 'commentable_type', 'commentable_id');
        $results = $relation->getResults();
        
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(0, $results);
    }

    public function testMorphOneReturnsSingleRelatedModel(): void
    {
        $post = new Post();
        $post->title = 'Test Post';
        $post->save();
        
        $image = new Image();
        $image->url = 'https://example.com/image.jpg';
        $image->imageable_type = Post::class;
        $image->imageable_id = $post->id;
        $image->save();
        
        $relation = new MorphOne($post, Image::class, 'imageable_type', 'imageable_id');
        $result = $relation->getResults();
        
        $this->assertInstanceOf(Image::class, $result);
        $this->assertSame($image->id, $result->id);
    }

    public function testMorphOneReturnsNullWhenNoRelatedModel(): void
    {
        $post = new Post();
        $post->title = 'Test Post';
        $post->save();
        
        $relation = new MorphOne($post, Image::class, 'imageable_type', 'imageable_id');
        $result = $relation->getResults();
        
        $this->assertNull($result);
    }

    public function testMorphManyUsesMorphClass(): void
    {
        $post = new Post();
        $post->title = 'Test Post';
        $post->save();
        
        // Create comment for post
        $comment1 = new Comment();
        $comment1->body = 'Post comment';
        $comment1->commentable_type = Post::class;
        $comment1->commentable_id = $post->id;
        $comment1->save();
        
        // Create comment for video (different type)
        $video = new Video();
        $video->title = 'Test Video';
        $video->save();
        
        $comment2 = new Comment();
        $comment2->body = 'Video comment';
        $comment2->commentable_type = Video::class;
        $comment2->commentable_id = $video->id;
        $comment2->save();
        
        $relation = new MorphMany($post, Comment::class, 'commentable_type', 'commentable_id');
        $results = $relation->getResults();
        
        // Should only return comments for the post
        $this->assertCount(1, $results);
        $this->assertSame('Post comment', $results->first()->body);
    }
}

class Post extends Model
{
    protected static string $table = 'posts';
    protected bool $timestamps = false;
}

class Video extends Model
{
    protected static string $table = 'videos';
    protected bool $timestamps = false;
}

class Comment extends Model
{
    protected static string $table = 'comments';
    protected bool $timestamps = false;
}

class Image extends Model
{
    protected static string $table = 'images';
    protected bool $timestamps = false;
}
