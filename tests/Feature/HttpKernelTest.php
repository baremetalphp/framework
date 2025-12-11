<?php

declare(strict_types=1);

namespace Tests\Feature;

use BareMetalPHP\Application;
use BareMetalPHP\Http\Kernel;
use BareMetalPHP\Http\Request;
use BareMetalPHP\Http\Response;
use BareMetalPHP\Routing\Router;
use Tests\TestCase;
use Tests\Feature\TestKernel;

class HttpKernelTest extends TestCase
{
    protected Kernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        
        $router = new Router($this->app);
        $this->kernel = new TestKernel($this->app, $router);
    }

    public function testKernelCanHandleRequest(): void
    {
        $router = new Router($this->app);
        $router->get('/test', fn() => new Response('Hello World'));
        $this->app->instance(Router::class, $router);
        
        $kernel = new TestKernel($this->app, $router);
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'], [], []);
        
        $response = $kernel->handle($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody());
    }

    public function testKernelReturns404ForNonExistentRoute(): void
    {
        // Ensure debug mode is off for this test
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['APP_DEBUG'] = 'false';
        
        $router = new Router($this->app);
        $kernel = new TestKernel($this->app, $router);
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/nonexistent'], [], []);
        
        $response = $kernel->handle($request);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testKernelHandlesExceptionsInDebugMode(): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = 'true';
        $_SERVER['APP_DEBUG'] = 'true';
        
        $router = new Router($this->app);
        $router->get('/error', fn() => throw new \RuntimeException('Test error'));
        $this->app->instance(Router::class, $router);
        
        $kernel = new TestKernel($this->app, $router);
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/error'], [], []);
        
        $response = $kernel->handle($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('RuntimeException', $response->getBody());
        
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['APP_DEBUG'] = 'false';
    }

    public function testKernelHandlesExceptionsInProductionMode(): void
    {
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['APP_DEBUG'] = 'false';
        
        $router = new Router($this->app);
        $router->get('/error', fn() => throw new \RuntimeException('Test error'));
        $this->app->instance(Router::class, $router);
        
        $kernel = new TestKernel($this->app, $router);
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/error'], [], []);
        
        $response = $kernel->handle($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Internal Server Error', $response->getBody());
    }

    public function testKernelHandlesApiRequestErrorsInProductionMode(): void
    {
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['APP_DEBUG'] = 'false';
        
        $router = new Router($this->app);
        $router->get('/api/error', fn() => throw new \RuntimeException('API error'));
        $this->app->instance(Router::class, $router);
        
        $kernel = new TestKernel($this->app, $router);
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/error'], [], []);
        
        $response = $kernel->handle($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('error', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertEquals('Internal Server Error', $body['error']);
        $this->assertEquals('API error', $body['message']);
        $this->assertArrayNotHasKey('file', $body); // No debug info in production
    }

    public function testKernelHandlesApiRequestErrorsInDebugMode(): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = 'true';
        $_SERVER['APP_DEBUG'] = 'true';
        
        $router = new Router($this->app);
        $router->get('/api/error', fn() => throw new \RuntimeException('API error'));
        $this->app->instance(Router::class, $router);
        
        $kernel = new TestKernel($this->app, $router);
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/error'], [], []);
        
        $response = $kernel->handle($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('error', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('file', $body); // Debug info included
        $this->assertArrayHasKey('line', $body);
        $this->assertArrayHasKey('trace', $body);
        $this->assertIsArray($body['trace']);
        
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['APP_DEBUG'] = 'false';
    }

    public function testKernelHandlesMiddlewareErrors(): void
    {
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['APP_DEBUG'] = 'false';
        
        $router = new Router($this->app);
        $router->get('/test', fn() => new Response('OK'));
        
        $middleware = new class {
            public function handle(Request $request, callable $next): Response
            {
                throw new \RuntimeException('Middleware error');
            }
        };
        
        $kernel = new class($this->app, $router) extends Kernel {
            protected array $middleware = [];
            
            public function setMiddleware(array $middleware): void
            {
                $this->middleware = $middleware;
            }
        };
        
        // Use reflection to set middleware for testing
        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('middleware');
        $property->setAccessible(true);
        $property->setValue($kernel, [get_class($middleware)]);
        
        $this->app->bind(get_class($middleware), fn() => $middleware);
        
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'], [], []);
        $response = $kernel->handle($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Internal Server Error', $response->getBody());
    }

    public function testKernelHandlesApiRequestWithDifferentApiPaths(): void
    {
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
        $_SERVER['APP_DEBUG'] = 'false';
        
        $router = new Router($this->app);
        $router->get('/api/v1/users', fn() => throw new \RuntimeException('Error'));
        $this->app->instance(Router::class, $router);
        
        $kernel = new TestKernel($this->app, $router);
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/users'], [], []);
        
        $response = $kernel->handle($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('error', $body);
    }
}

