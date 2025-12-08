<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Application;
use BareMetalPHP\Support\Facades\Facade;
use Tests\TestCase;

class FacadeTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clear facade instances
        TestFacade::clearResolvedInstances();
        TestFacade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function testCanSetFacadeApplication(): void
    {
        $app = new Application();
        TestFacade::setFacadeApplication($app);
        
        $this->assertSame($app, TestFacade::getFacadeApplication());
    }

    public function testGetFacadeApplicationReturnsGlobalInstanceWhenNotSet(): void
    {
        Application::setInstance($this->app);
        TestFacade::setFacadeApplication(null);
        
        $this->assertSame($this->app, TestFacade::getFacadeApplication());
    }

    public function testResolveFacadeInstanceFromContainer(): void
    {
        $service = new \stdClass();
        $service->value = 'test';
        
        $this->app->bind('test.service', fn() => $service);
        
        TestFacade::setFacadeApplication($this->app);
        
        $instance = TestFacade::getFacadeRoot();
        
        $this->assertSame($service, $instance);
    }

    public function testResolveFacadeInstanceCachesResult(): void
    {
        $callCount = 0;
        $this->app->bind('test.service', function() use (&$callCount) {
            $callCount++;
            return new \stdClass();
        });
        
        TestFacade::setFacadeApplication($this->app);
        
        TestFacade::getFacadeRoot();
        TestFacade::getFacadeRoot();
        
        // Should only be called once due to caching
        $this->assertSame(1, $callCount);
    }

    public function testClearResolvedInstance(): void
    {
        $callCount = 0;
        $this->app->bind('test.service', function() use (&$callCount) {
            $callCount++;
            return new \stdClass();
        });
        
        TestFacade::setFacadeApplication($this->app);
        
        TestFacade::getFacadeRoot();
        TestFacade::clearResolvedInstance('test.service');
        TestFacade::getFacadeRoot();
        
        // Should be called twice after clearing
        $this->assertSame(2, $callCount);
    }

    public function testClearResolvedInstances(): void
    {
        $this->app->bind('test.service', fn() => new \stdClass());
        
        TestFacade::setFacadeApplication($this->app);
        
        TestFacade::getFacadeRoot();
        TestFacade::clearResolvedInstances();
        
        $reflection = new \ReflectionClass(TestFacade::class);
        $property = $reflection->getProperty('resolvedInstances');
        $property->setAccessible(true);
        
        $this->assertEmpty($property->getValue());
    }

    public function testFacadeThrowsExceptionWhenApplicationNotAvailable(): void
    {
        TestFacade::setFacadeApplication(null);
        Application::setInstance(null);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Application instance is not available');
        
        TestFacade::getFacadeRoot();
    }

    public function testFacadeMagicMethodCallsUnderlyingInstance(): void
    {
        $service = new class {
            public function testMethod($arg): string {
                return "Called with: {$arg}";
            }
        };
        
        $this->app->bind('test.service', fn() => $service);
        TestFacade::setFacadeApplication($this->app);
        
        $result = TestFacade::testMethod('test');
        
        $this->assertSame('Called with: test', $result);
    }
}

class TestFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'test.service';
    }
    
    public static function getFacadeRoot(): object
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }
}
