<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use BareMetalPHP\Application;
use BareMetalPHP\Exceptions\ErrorPageRenderer;
use BareMetalPHP\Http\Request;
use Tests\TestCase;

class ErrorPageRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = 'true';
    }

    protected function tearDown(): void
    {
        putenv('APP_DEBUG');
        unset($_ENV['APP_DEBUG']);
        parent::tearDown();
    }

    public function testRenderReturnsHtml(): void
    {
        $exception = new \RuntimeException('Test error message');
        $html = ErrorPageRenderer::render($exception);
        
        $this->assertIsString($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html', $html);
    }

    public function testRenderIncludesExceptionClass(): void
    {
        $exception = new \InvalidArgumentException('Test');
        $html = ErrorPageRenderer::render($exception);
        
        $this->assertStringContainsString('InvalidArgumentException', $html);
    }

    public function testRenderIncludesExceptionMessage(): void
    {
        $exception = new \RuntimeException('Custom error message');
        $html = ErrorPageRenderer::render($exception);
        
        $this->assertStringContainsString('Custom error message', $html);
    }

    public function testRenderIncludesFileAndLine(): void
    {
        $exception = new \Exception('Test');
        $html = ErrorPageRenderer::render($exception);
        
        $this->assertStringContainsString($exception->getFile(), $html);
        $this->assertStringContainsString((string)$exception->getLine(), $html);
    }

    public function testRenderWithRequest(): void
    {
        $exception = new \RuntimeException('Test');
        $request = Request::fromParts(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'], '');
        
        $html = ErrorPageRenderer::render($exception, $request);
        
        $this->assertStringContainsString('/test', $html);
        $this->assertStringContainsString('GET', $html);
    }

    public function testRenderWithApplication(): void
    {
        $exception = new \RuntimeException('Test');
        $html = ErrorPageRenderer::render($exception, null, $this->app);
        
        $this->assertStringContainsString('Container', $html);
        $this->assertStringContainsString('Bindings', $html);
    }

    public function testRenderIncludesCodeExcerpt(): void
    {
        $exception = new \RuntimeException('Test');
        $html = ErrorPageRenderer::render($exception);
        
        $this->assertStringContainsString('Code Excerpt', $html);
        $this->assertStringContainsString('code-block', $html);
    }

    public function testRenderIncludesStackTrace(): void
    {
        $exception = new \RuntimeException('Test');
        $html = ErrorPageRenderer::render($exception);
        
        $this->assertStringContainsString('Stack Trace', $html);
        $this->assertStringContainsString('trace', $html);
    }

    public function testRenderIncludesRequestContext(): void
    {
        $exception = new \RuntimeException('Test');
        $request = Request::fromParts(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/users'], '');
        
        $html = ErrorPageRenderer::render($exception, $request);
        
        $this->assertStringContainsString('Request Context', $html);
        $this->assertStringContainsString('POST', $html);
        $this->assertStringContainsString('/api/users', $html);
    }

    public function testRenderEscapesHtml(): void
    {
        $exception = new \RuntimeException('<script>alert("xss")</script>');
        $html = ErrorPageRenderer::render($exception);
        
        // Should escape HTML in exception message
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}

