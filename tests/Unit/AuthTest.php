<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Auth\Auth;
use BareMetalPHP\Support\Session;
use Tests\TestCase;

class AuthTest extends TestCase
{
    protected bool $needsDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Start session for testing
        Session::start();
        Session::flush();
    }

    protected function tearDown(): void
    {
        Session::destroy();
        parent::tearDown();
    }

    public function testCheckReturnsFalseWhenNotAuthenticated(): void
    {
        $this->assertFalse(Auth::check());
    }

    public function testUserReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull(Auth::user());
    }

    public function testIdReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull(Auth::id());
    }

    public function testLoginSetsUserIdInSession(): void
    {
        $user = $this->createTestUser();
        Auth::login($user);

        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
        $this->assertSame($user->id, Auth::user()->id);
    }

    public function testLogoutRemovesUserFromSession(): void
    {
        $user = $this->createTestUser();
        Auth::login($user);
        
        $this->assertTrue(Auth::check());
        
        Auth::logout();
        
        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::user());
        $this->assertNull(Auth::id());
    }

    public function testAttemptReturnsNullForInvalidEmail(): void
    {
        $result = Auth::attempt('nonexistent@example.com', 'password');
        
        $this->assertNull($result);
        $this->assertFalse(Auth::check());
    }

    public function testAttemptReturnsNullForInvalidPassword(): void
    {
        $user = $this->createTestUser();
        
        $result = Auth::attempt($user->email, 'wrong-password');
        
        $this->assertNull($result);
        $this->assertFalse(Auth::check());
    }

    public function testAttemptLogsInUserWithValidCredentials(): void
    {
        $user = $this->createTestUser();
        $password = 'test-password-123';
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->save();
        
        $result = Auth::attempt($user->email, $password);
        
        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
    }

    public function testAttemptIsCaseInsensitiveForEmail(): void
    {
        $user = $this->createTestUser();
        $password = 'test-password-123';
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->save();
        
        $result = Auth::attempt(strtoupper($user->email), $password);
        
        $this->assertNotNull($result);
        $this->assertTrue(Auth::check());
    }

    public function testAttemptTrimsEmailWhitespace(): void
    {
        $user = $this->createTestUser();
        $password = 'test-password-123';
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->save();
        
        $result = Auth::attempt('  ' . $user->email . '  ', $password);
        
        $this->assertNotNull($result);
        $this->assertTrue(Auth::check());
    }

    protected function createTestUser(): User
    {
        $this->needsDatabase = true;
        $this->setUpDatabase();
        
        $this->createTable('users', <<<SQL
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                password TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        SQL);
        
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->save();
        
        return $user;
    }
}

