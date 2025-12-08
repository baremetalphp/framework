<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Routing\RouteDefinition;
use BareMetalPHP\Routing\Router;
use BareMetalPHP\Routing\SPARouteHelper;
use Tests\TestCase;

class SPARouteHelperTest extends TestCase
{
    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router($this->app);
    }

    public function testSpaRegistersBaseRoute(): void
    {
        $route = $this->router->spa('/app/*', fn() => 'SPA');
        
        $this->assertInstanceOf(RouteDefinition::class, $route);
        
        // Check that base route exists by creating a request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/app';
        $request = \BareMetalPHP\Http\Request::fromGlobals();
        
        $response = $this->router->dispatch($request);
        $this->assertNotSame(404, $response->getStatusCode());
    }

    public function testSpaRegistersCatchAllRoute(): void
    {
        $this->router->spa('/app/*', fn() => 'SPA');
        
        // Check that catch-all route exists
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/app/users';
        $request1 = \BareMetalPHP\Http\Request::fromGlobals();
        $response1 = $this->router->dispatch($request1);
        $this->assertNotSame(404, $response1->getStatusCode());
        
        $_SERVER['REQUEST_URI'] = '/app/users/1';
        $request2 = \BareMetalPHP\Http\Request::fromGlobals();
        $response2 = $this->router->dispatch($request2);
        $this->assertNotSame(404, $response2->getStatusCode());
    }

    public function testSpaWithoutWildcardRegistersNormalRoute(): void
    {
        $route = $this->router->spa('/app', fn() => 'SPA');
        
        $this->assertInstanceOf(RouteDefinition::class, $route);
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/app';
        $request = \BareMetalPHP\Http\Request::fromGlobals();
        $response = $this->router->dispatch($request);
        $this->assertNotSame(404, $response->getStatusCode());
        
        // Should not match sub-paths
        $_SERVER['REQUEST_URI'] = '/app/users';
        $request2 = \BareMetalPHP\Http\Request::fromGlobals();
        $response2 = $this->router->dispatch($request2);
        $this->assertSame(404, $response2->getStatusCode());
    }

    public function testSpaWithCustomMethod(): void
    {
        $this->router->spa('/api/*', fn() => 'API', 'POST');
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/data';
        $request1 = \BareMetalPHP\Http\Request::fromGlobals();
        $response1 = $this->router->dispatch($request1);
        $this->assertNotSame(404, $response1->getStatusCode());
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request2 = \BareMetalPHP\Http\Request::fromGlobals();
        $response2 = $this->router->dispatch($request2);
        $this->assertSame(404, $response2->getStatusCode());
    }

    public function testSpaReturnsBaseRoute(): void
    {
        $route = $this->router->spa('/app/*', fn() => 'SPA');
        
        $this->assertInstanceOf(RouteDefinition::class, $route);
        $this->assertSame('/app', $route->getUri());
    }
}
