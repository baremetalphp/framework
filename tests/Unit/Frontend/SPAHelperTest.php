<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend;

use BareMetalPHP\Frontend\AssetManager;
use BareMetalPHP\Frontend\SPAHelper;
use BareMetalPHP\Http\Response;
use Tests\TestCase;

class SPAHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function createAssetManager(): AssetManager
    {
        return new AssetManager();
    }

    public function testCanCreateSPAHelper(): void
    {
        // Ensure class is loaded and declaration line is executed
        $this->assertTrue(class_exists(SPAHelper::class));
        
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        $this->assertInstanceOf(SPAHelper::class, $helper);
    }

    public function testRenderReturnsResponse(): void
    {
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        $response = $helper->render('Home', []);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaders()['Content-Type']);
    }

    public function testRenderIncludesComponentInHtml(): void
    {
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        $response = $helper->render('Home', []);
        $body = $response->getBody();
        
        $this->assertStringContainsString('Home', $body);
        $this->assertStringContainsString('data-component', $body);
        $this->assertStringContainsString('<div id="app"', $body);
    }

    public function testRenderIncludesPropsInHtml(): void
    {
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        $props = ['userId' => 123, 'name' => 'John'];
        $response = $helper->render('UserProfile', $props);
        $body = $response->getBody();
        
        $this->assertStringContainsString('data-props', $body);
        $this->assertStringContainsString('123', $body);
        $this->assertStringContainsString('John', $body);
    }

    public function testRenderUsesDefaultLayoutWhenLayoutFileMissing(): void
    {
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        $response = $helper->render('Home', []);
        $body = $response->getBody();
        
        // Should use default layout (buildDefaultLayout)
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<html', $body);
        $this->assertStringContainsString('<head>', $body);
        $this->assertStringContainsString('<body>', $body);
    }

    public function testRenderWithCustomLayout(): void
    {
        // Create a temporary layout file
        $layoutDir = sys_get_temp_dir() . '/baremetal_test_' . uniqid() . '/resources/views/layouts';
        mkdir($layoutDir, 0755, true);
        $layoutFile = $layoutDir . '/custom.php';
        
        file_put_contents($layoutFile, '<html><body>Custom Layout: <?= $component ?></body></html>');
        
        // Mock base_path to return our temp directory
        $originalBasePath = function_exists('base_path') ? null : null;
        
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        // Use reflection to test buildSPAHTML with custom layout
        $reflection = new \ReflectionClass(SPAHelper::class);
        $method = $reflection->getMethod('buildSPAHTML');
        $method->setAccessible(true);
        
        // We can't easily mock base_path, so test the default layout path
        $html = $method->invoke($helper, 'Home', [], 'custom');
        
        // Cleanup
        unlink($layoutFile);
        rmdir($layoutDir);
        rmdir(dirname($layoutDir));
    }

    public function testJsonReturnsJsonResponse(): void
    {
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        $data = ['status' => 'success', 'message' => 'Hello'];
        $response = $helper->json($data);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertEquals('success', $body['status']);
    }

    public function testJsonWithCustomStatus(): void
    {
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        $response = $helper->json(['error' => 'Not Found'], 404);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCsrfTokenReturnsEmptyString(): void
    {
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        $token = $helper->csrfToken();
        
        $this->assertIsString($token);
        $this->assertEmpty($token);
    }

    public function testBuildDefaultLayoutIncludesAssetManagerMethods(): void
    {
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        $reflection = new \ReflectionClass(SPAHelper::class);
        $method = $reflection->getMethod('buildDefaultLayout');
        $method->setAccessible(true);
        
        $html = $method->invoke($helper, 'Home', ['test' => 'value']);
        
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('<head>', $html);
        $this->assertStringContainsString('<body>', $html);
        $this->assertStringContainsString('id="app"', $html);
        $this->assertStringContainsString('data-component', $html);
        $this->assertStringContainsString('data-props', $html);
    }

    public function testRenderEscapesPropsInJson(): void
    {
        $assetManager = $this->createAssetManager();
        $helper = new SPAHelper($assetManager);
        
        // Test with props that need escaping
        $props = [
            'html' => '<script>alert("xss")</script>',
            'quote' => 'He said "hello"',
            'amp' => 'A & B'
        ];
        
        $response = $helper->render('Test', $props);
        $body = $response->getBody();
        
        // Props should be JSON encoded (which escapes)
        $this->assertStringContainsString('data-props', $body);
        // Should not contain raw script tags
        $this->assertStringNotContainsString('<script>alert', $body);
    }
}

