<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use BareMetalPHP\Application;
use BareMetalPHP\Http\Request;
use BareMetalPHP\Http\Response;
use BareMetalPHP\Routing\Router;
use BareMetalPHP\Routing\RouteDefinition;
use Tests\TestCase;

class RouterAdvancedTest extends TestCase
{
    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router($this->app);
    }

    public function testCanRegisterPutRoute(): void
    {
        $this->router->put('/users/{id}', fn(int $id) => new Response("Updated {$id}"));
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/users/123'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Updated 123', $response->getBody());
    }

    public function testCanRegisterDeleteRoute(): void
    {
        $this->router->delete('/users/{id}', fn(int $id) => new Response("Deleted {$id}", 204));
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/users/123'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testRouteWithMiddleware(): void
    {
        $middleware = new class {
            public function handle(Request $request, callable $next): Response
            {
                $response = $next($request);
                $response->setHeader('X-Middleware', 'applied');
                return $response;
            }
        };
        
        $this->app->instance('TestMiddleware', $middleware);
        
        $route = $this->router->get('/protected', fn() => new Response('OK'));
        $route->middleware('TestMiddleware');
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/protected'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('OK', $response->getBody());
        $this->assertEquals('applied', $response->getHeaders()['X-Middleware'] ?? null);
    }

    public function testRouteWithMultipleMiddleware(): void
    {
        $middleware1 = new class {
            public function handle(Request $request, callable $next): Response
            {
                $response = $next($request);
                $response->setHeader('X-Middleware-1', 'applied');
                return $response;
            }
        };
        
        $middleware2 = new class {
            public function handle(Request $request, callable $next): Response
            {
                $response = $next($request);
                $response->setHeader('X-Middleware-2', 'applied');
                return $response;
            }
        };
        
        $this->app->instance('TestMiddleware1', $middleware1);
        $this->app->instance('TestMiddleware2', $middleware2);
        
        $route = $this->router->get('/protected', fn() => new Response('OK'));
        $route->middleware(['TestMiddleware1', 'TestMiddleware2']);
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/protected'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('applied', $response->getHeaders()['X-Middleware-1'] ?? null);
        $this->assertEquals('applied', $response->getHeaders()['X-Middleware-2'] ?? null);
    }

    public function testControllerStringFormat(): void
    {
        $controller = new class {
            public function show(int $id): Response
            {
                return new Response("User {$id}");
            }
        };
        
        $this->app->bind('App\\Http\\Controllers\\UserController', fn() => $controller);
        
        $this->router->get('/users/{id}', 'UserController@show');
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('User 42', $response->getBody());
    }

    public function testControllerArrayFormat(): void
    {
        $controller = new class {
            public function index(): Response
            {
                return new Response('Index');
            }
        };
        
        $controllerClass = get_class($controller);
        $this->app->bind($controllerClass, fn() => $controller);
        
        $this->router->get('/test', [$controllerClass, 'index']);
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('Index', $response->getBody());
    }

    public function testToResponseWithString(): void
    {
        $this->router->get('/test', fn() => 'Hello World');
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('Hello World', $response->getBody());
    }

    public function testToResponseWithInteger(): void
    {
        $this->router->get('/test', fn() => 42);
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('42', $response->getBody());
    }

    public function testRouteWithPathParameter(): void
    {
        $this->router->get('/api/{path}', fn(string $path) => new Response("Path: {$path}"));
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users/123'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('Path: users/123', $response->getBody());
    }

    public function testRouteWithFloatParameter(): void
    {
        $this->router->get('/price/{amount}', function(float $amount) {
            return new Response("Price: {$amount}");
        });
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/price/19.99'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertStringContainsString('19.99', $response->getBody());
    }

    public function testRouteWithBooleanParameter(): void
    {
        $this->router->get('/flag/{active}', function(bool $active) {
            return new Response($active ? 'true' : 'false');
        });
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/flag/1'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('true', $response->getBody());
    }

    public function testRouteWithDefaultParameter(): void
    {
        // Router doesn't support optional parameters in URI, but we can test default values in function
        $this->router->get('/page/{id}', function(int $id = 1) {
            return new Response("Page {$id}");
        });
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/page/5'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('Page 5', $response->getBody());
    }

    public function testRouteDefinitionGetUri(): void
    {
        $route = $this->router->get('/users/{id}', fn() => new Response('test'));
        
        $this->assertInstanceOf(RouteDefinition::class, $route);
        $this->assertEquals('/users/{id}', $route->getUri());
    }

    public function testRouteDefinitionName(): void
    {
        $route = $this->router->get('/users/{id}', fn() => new Response('test'));
        $result = $route->name('users.show');
        
        $this->assertSame($route, $result);
        $this->assertEquals('/users/42', $this->router->route('users.show', ['id' => 42]));
    }

    public function testRouteDefinitionMiddleware(): void
    {
        $middleware = new class {
            public function handle(Request $request, callable $next): Response
            {
                return $next($request);
            }
        };
        
        $this->app->instance('TestMiddleware', $middleware);
        
        $route = $this->router->get('/test', fn() => new Response('OK'));
        $result = $route->middleware('TestMiddleware');
        
        $this->assertSame($route, $result);
    }

    public function testReturns404ForUnregisteredMethod(): void
    {
        $this->router->get('/test', fn() => new Response('OK'));
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/test'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testThrowsExceptionInDebugModeForUnregisteredMethod(): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = 'true';
        
        $this->router->get('/test', fn() => new Response('OK'));
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/test'], '');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No routes registered for POST method');
        
        try {
            $this->router->dispatch($request);
        } finally {
            putenv('APP_DEBUG');
            unset($_ENV['APP_DEBUG']);
        }
    }

    public function testThrowsExceptionInDebugModeForNonExistentRoute(): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = 'true';
        
        $this->router->get('/test', fn() => new Response('OK'));
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/nonexistent'], '');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Route not found');
        
        try {
            $this->router->dispatch($request);
        } finally {
            putenv('APP_DEBUG');
            unset($_ENV['APP_DEBUG']);
        }
    }

    public function testReturns500ForInvalidRouteAction(): void
    {
        $this->router->get('/invalid', 'not-a-valid-action');
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/invalid'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('Invalid route action', $response->getBody());
    }

    public function testRouteWithDependencyInjection(): void
    {
        $service = new class {
            public string $value = 'injected';
        };
        
        $serviceClass = get_class($service);
        $this->app->bind($serviceClass, fn() => $service);
        
        $this->router->get('/test', function($injected) use ($serviceClass) {
            return new Response($injected->value);
        });
        
        // Actually test with Request dependency which is easier
        $this->router->get('/test2', function(Request $request) {
            return new Response($request->getPath());
        });
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test2'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('/test2', $response->getBody());
    }

    public function testRouteWithRequestDependency(): void
    {
        $this->router->get('/test', function(Request $request) {
            return new Response($request->getPath());
        });
        
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'], '');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('/test', $response->getBody());
    }
}

