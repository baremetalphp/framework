<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Support\Session;
use Tests\TestCase;

class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Session::start();
        Session::flush();
    }

    protected function tearDown(): void
    {
        Session::destroy();
        parent::tearDown();
    }

    public function testCanSetAndGetValue(): void
    {
        Session::set('test_key', 'test_value');
        
        $this->assertSame('test_value', Session::get('test_key'));
    }

    public function testGetReturnsDefaultWhenKeyDoesNotExist(): void
    {
        $this->assertNull(Session::get('nonexistent'));
        $this->assertSame('default', Session::get('nonexistent', 'default'));
    }

    public function testHasReturnsTrueWhenKeyExists(): void
    {
        Session::set('test_key', 'test_value');
        
        $this->assertTrue(Session::has('test_key'));
    }

    public function testHasReturnsFalseWhenKeyDoesNotExist(): void
    {
        $this->assertFalse(Session::has('nonexistent'));
    }

    public function testRemoveDeletesKey(): void
    {
        Session::set('test_key', 'test_value');
        $this->assertTrue(Session::has('test_key'));
        
        Session::remove('test_key');
        
        $this->assertFalse(Session::has('test_key'));
        $this->assertNull(Session::get('test_key'));
    }

    public function testFlashStoresValueForNextRequest(): void
    {
        Session::flash('message', 'Flash message');
        
        $this->assertSame('Flash message', Session::getFlash('message'));
    }

    public function testGetFlashRemovesValueAfterReading(): void
    {
        Session::flash('message', 'Flash message');
        
        $value1 = Session::getFlash('message');
        $value2 = Session::getFlash('message');
        
        $this->assertSame('Flash message', $value1);
        $this->assertNull($value2);
    }

    public function testGetFlashReturnsDefaultWhenKeyDoesNotExist(): void
    {
        $this->assertNull(Session::getFlash('nonexistent'));
        $this->assertSame('default', Session::getFlash('nonexistent', 'default'));
    }

    public function testFlushClearsAllSessionData(): void
    {
        Session::set('key1', 'value1');
        Session::set('key2', 'value2');
        Session::flash('flash_key', 'flash_value');
        
        Session::flush();
        
        $this->assertNull(Session::get('key1'));
        $this->assertNull(Session::get('key2'));
        $this->assertNull(Session::getFlash('flash_key'));
    }

    public function testRegenerateChangesSessionId(): void
    {
        $oldId = session_id();
        
        Session::regenerate();
        
        $newId = session_id();
        
        $this->assertNotSame($oldId, $newId);
    }

    public function testStartIsIdempotent(): void
    {
        Session::start();
        $id1 = session_id();
        
        Session::start();
        $id2 = session_id();
        
        $this->assertSame($id1, $id2);
    }

    public function testDestroyEndsSession(): void
    {
        Session::set('test_key', 'test_value');
        $this->assertTrue(Session::has('test_key'));
        
        Session::destroy();
        
        // After destroy, session should be reset
        Session::start();
        $this->assertFalse(Session::has('test_key'));
    }
}
