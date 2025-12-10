<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use BareMetalPHP\Exceptions\ErrorHandler;
use BareMetalPHP\Http\Response;
use Tests\TestCase;

class ErrorHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
    }

    protected function tearDown(): void
    {
        putenv('APP_DEBUG');
        unset($_ENV['APP_DEBUG']);
        parent::tearDown();
    }

    public function testHandleReturnsProductionResponse(): void
    {
        $handler = new ErrorHandler();
        $exception = new \RuntimeException('Test error');
        
        $response = $handler->handle($exception);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Internal Server Error', $response->getBody());
    }

    public function testHandleReturnsDebugResponseInDebugMode(): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = 'true';
        
        $handler = new ErrorHandler();
        $exception = new \RuntimeException('Test error message');
        
        $response = $handler->handle($exception);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaders()['Content-Type']);
        $this->assertStringContainsString('Test error message', $response->getBody());
        $this->assertStringContainsString('RuntimeException', $response->getBody());
        
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
    }

    public function testDebugResponseIncludesExceptionDetails(): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = 'true';
        
        $handler = new ErrorHandler();
        $exception = new \InvalidArgumentException('Invalid argument', 400);
        
        $response = $handler->handle($exception);
        $body = $response->getBody();
        
        $this->assertStringContainsString('InvalidArgumentException', $body);
        $this->assertStringContainsString('Invalid argument', $body);
        $this->assertStringContainsString($exception->getFile(), $body);
        $this->assertStringContainsString((string)$exception->getLine(), $body);
        $this->assertStringContainsString('Trace:', $body);
        
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
    }

    public function testDebugResponseIncludesStackTrace(): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = 'true';
        
        $handler = new ErrorHandler();
        $exception = new \Exception('Test');
        
        $response = $handler->handle($exception);
        $body = $response->getBody();
        
        $this->assertStringContainsString('getTraceAsString', $body);
        
        putenv('APP_DEBUG=false');
        $_ENV['APP_DEBUG'] = 'false';
    }
}

