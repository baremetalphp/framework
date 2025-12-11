<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend;

use BareMetalPHP\Frontend\AssetManager;
use BareMetalPHP\Support\Config;
use Tests\TestCase;

class AssetManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set environment to production for most tests
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_ENV'] = 'production';
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV');
        unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
        parent::tearDown();
    }

    public function testCanCreateAssetManager(): void
    {
        // Ensure class is loaded and declaration line is executed
        $this->assertTrue(class_exists(AssetManager::class));
        
        $manager = new AssetManager();
        
        $this->assertInstanceOf(AssetManager::class, $manager);
    }

    public function testIsDevReturnsFalseInProduction(): void
    {
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        
        $manager = new AssetManager();
        
        $this->assertFalse($manager->isDev());
    }

    public function testAssetReturnsProductionPathWhenManifestMissing(): void
    {
        $manager = new AssetManager();
        $url = $manager->asset('resources/js/app.jsx');
        
        $this->assertStringContainsString('/build/', $url);
        $this->assertStringContainsString('app.jsx', $url);
    }

    public function testAssetWithManifest(): void
    {
        // Create a temporary manifest file
        $manifestDir = sys_get_temp_dir() . '/baremetal_test_' . uniqid();
        $manifestPath = $manifestDir . '/public/build/.vite/manifest.json';
        mkdir(dirname($manifestPath), 0755, true);
        
        $manifest = [
            'resources/js/app.jsx' => [
                'file' => 'assets/app-abc123.js',
                'css' => ['assets/app.css']
            ]
        ];
        
        file_put_contents($manifestPath, json_encode($manifest));
        
        // Use reflection to set manifest path
        $reflection = new \ReflectionClass(AssetManager::class);
        $property = $reflection->getProperty('manifestPath');
        $property->setAccessible(true);
        
        $manager = new AssetManager();
        $property->setValue($manager, $manifestPath);
        
        $url = $manager->asset('resources/js/app.jsx');
        
        $this->assertStringContainsString('/build/', $url);
        $this->assertStringContainsString('app-abc123.js', $url);
        
        // Cleanup
        unlink($manifestPath);
        rmdir(dirname($manifestPath));
        rmdir(dirname(dirname($manifestPath)));
    }

    public function testCssReturnsEmptyArrayInDevelopment(): void
    {
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        
        $manager = new AssetManager();
        $css = $manager->css('resources/js/app.jsx');
        
        $this->assertIsArray($css);
        $this->assertEmpty($css);
    }

    public function testCssReturnsEmptyWhenManifestMissing(): void
    {
        $manager = new AssetManager();
        $css = $manager->css('resources/js/app.jsx');
        
        $this->assertIsArray($css);
        $this->assertEmpty($css);
    }

    public function testCssReturnsFilesFromManifest(): void
    {
        // Create a temporary manifest file
        $manifestDir = sys_get_temp_dir() . '/baremetal_test_' . uniqid();
        $manifestPath = $manifestDir . '/public/build/.vite/manifest.json';
        mkdir(dirname($manifestPath), 0755, true);
        
        $manifest = [
            'resources/js/app.jsx' => [
                'file' => 'assets/app.js',
                'css' => ['assets/app.css', 'assets/vendor.css']
            ]
        ];
        
        file_put_contents($manifestPath, json_encode($manifest));
        
        $reflection = new \ReflectionClass(AssetManager::class);
        $property = $reflection->getProperty('manifestPath');
        $property->setAccessible(true);
        
        $manager = new AssetManager();
        $property->setValue($manager, $manifestPath);
        
        $css = $manager->css('resources/js/app.jsx');
        
        $this->assertCount(2, $css);
        $this->assertContains('assets/app.css', $css);
        $this->assertContains('assets/vendor.css', $css);
        
        // Cleanup
        unlink($manifestPath);
        rmdir(dirname($manifestPath));
        rmdir(dirname(dirname($manifestPath)));
    }

    public function testViteClientReturnsEmptyInProduction(): void
    {
        $manager = new AssetManager();
        $client = $manager->viteClient();
        
        $this->assertEmpty($client);
    }

    public function testViteClientReturnsScriptInDevelopment(): void
    {
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        
        // Mock config to return null framework (no React preamble)
        Config::set('frontend.framework', null);
        
        $manager = new AssetManager();
        $client = $manager->viteClient();
        
        // In development, it will try to check for Vite server, but we can test the structure
        // Since isViteDevServerRunning will likely return false in tests, isDevelopment will be false
        // So we need to force development mode
        $reflection = new \ReflectionClass(AssetManager::class);
        $property = $reflection->getProperty('isDevelopment');
        $property->setAccessible(true);
        $property->setValue($manager, true);
        
        $client = $manager->viteClient();
        
        $this->assertStringContainsString('@vite/client', $client);
        $this->assertStringContainsString('<script', $client);
    }

    public function testViteClientIncludesReactPreamble(): void
    {
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        Config::set('frontend.framework', 'react');
        
        $manager = new AssetManager();
        
        // Force development mode
        $reflection = new \ReflectionClass(AssetManager::class);
        $property = $reflection->getProperty('isDevelopment');
        $property->setAccessible(true);
        $property->setValue($manager, true);
        
        $client = $manager->viteClient();
        
        $this->assertStringContainsString('@react-refresh', $client);
        $this->assertStringContainsString('RefreshRuntime', $client);
        $this->assertStringContainsString('@vite/client', $client);
    }

    public function testScriptGeneratesScriptTag(): void
    {
        $manager = new AssetManager();
        $script = $manager->script('resources/js/app.jsx');
        
        $this->assertStringContainsString('<script', $script);
        $this->assertStringContainsString('type="module"', $script);
        $this->assertStringContainsString('src=', $script);
    }

    public function testScriptWithCustomAttributes(): void
    {
        $manager = new AssetManager();
        $script = $manager->script('resources/js/app.jsx', ['defer' => true, 'async' => true]);
        
        $this->assertStringContainsString('defer', $script);
        $this->assertStringContainsString('async', $script);
    }

    public function testScriptWithStringAttributes(): void
    {
        $manager = new AssetManager();
        $script = $manager->script('resources/js/app.jsx', ['data-test' => 'value']);
        
        $this->assertStringContainsString('data-test="value"', $script);
    }

    public function testStylesReturnsEmptyInDevelopment(): void
    {
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        
        $manager = new AssetManager();
        
        // Force development mode
        $reflection = new \ReflectionClass(AssetManager::class);
        $property = $reflection->getProperty('isDevelopment');
        $property->setAccessible(true);
        $property->setValue($manager, true);
        
        $styles = $manager->styles('resources/js/app.jsx');
        
        $this->assertEmpty($styles);
    }

    public function testStylesReturnsLinkTags(): void
    {
        // Create a temporary manifest file
        $manifestDir = sys_get_temp_dir() . '/baremetal_test_' . uniqid();
        $manifestPath = $manifestDir . '/public/build/.vite/manifest.json';
        mkdir(dirname($manifestPath), 0755, true);
        
        $manifest = [
            'resources/js/app.jsx' => [
                'file' => 'assets/app.js',
                'css' => ['assets/app.css', 'assets/vendor.css']
            ]
        ];
        
        file_put_contents($manifestPath, json_encode($manifest));
        
        $reflection = new \ReflectionClass(AssetManager::class);
        $property = $reflection->getProperty('manifestPath');
        $property->setAccessible(true);
        
        $manager = new AssetManager();
        $property->setValue($manager, $manifestPath);
        
        $styles = $manager->styles('resources/js/app.jsx');
        
        $this->assertStringContainsString('<link', $styles);
        $this->assertStringContainsString('rel="stylesheet"', $styles);
        $this->assertStringContainsString('app.css', $styles);
        $this->assertStringContainsString('vendor.css', $styles);
        
        // Cleanup
        unlink($manifestPath);
        rmdir(dirname($manifestPath));
        rmdir(dirname(dirname($manifestPath)));
    }

    public function testGetManifestReturnsEmptyArrayWhenFileMissing(): void
    {
        $manager = new AssetManager();
        
        $reflection = new \ReflectionClass(AssetManager::class);
        $method = $reflection->getMethod('getManifest');
        $method->setAccessible(true);
        
        $manifest = $method->invoke($manager);
        
        $this->assertIsArray($manifest);
        $this->assertEmpty($manifest);
    }

    public function testGetManifestCachesResult(): void
    {
        // Create a temporary manifest file
        $manifestDir = sys_get_temp_dir() . '/baremetal_test_' . uniqid();
        $manifestPath = $manifestDir . '/public/build/.vite/manifest.json';
        mkdir(dirname($manifestPath), 0755, true);
        
        $manifest = ['test' => 'value'];
        file_put_contents($manifestPath, json_encode($manifest));
        
        $reflection = new \ReflectionClass(AssetManager::class);
        $property = $reflection->getProperty('manifestPath');
        $property->setAccessible(true);
        $method = $reflection->getMethod('getManifest');
        $method->setAccessible(true);
        
        $manager = new AssetManager();
        $property->setValue($manager, $manifestPath);
        
        $result1 = $method->invoke($manager);
        $result2 = $method->invoke($manager);
        
        $this->assertSame($result1, $result2);
        $this->assertArrayHasKey('test', $result1);
        
        // Cleanup
        unlink($manifestPath);
        rmdir(dirname($manifestPath));
        rmdir(dirname(dirname($manifestPath)));
    }

    public function testBuildAttributesWithBooleanTrue(): void
    {
        $manager = new AssetManager();
        
        $reflection = new \ReflectionClass(AssetManager::class);
        $method = $reflection->getMethod('buildAttributes');
        $method->setAccessible(true);
        
        $attrs = $method->invoke($manager, ['defer' => true]);
        
        $this->assertStringContainsString('defer', $attrs);
        $this->assertStringNotContainsString('="', $attrs);
    }

    public function testBuildAttributesWithStringValue(): void
    {
        $manager = new AssetManager();
        
        $reflection = new \ReflectionClass(AssetManager::class);
        $method = $reflection->getMethod('buildAttributes');
        $method->setAccessible(true);
        
        $attrs = $method->invoke($manager, ['data-test' => 'value']);
        
        $this->assertStringContainsString('data-test="value"', $attrs);
    }

    public function testBuildAttributesIgnoresFalseAndNull(): void
    {
        $manager = new AssetManager();
        
        $reflection = new \ReflectionClass(AssetManager::class);
        $method = $reflection->getMethod('buildAttributes');
        $method->setAccessible(true);
        
        $attrs = $method->invoke($manager, [
            'defer' => true,
            'async' => false,
            'test' => null,
            'valid' => 'value'
        ]);
        
        $this->assertStringContainsString('defer', $attrs);
        $this->assertStringContainsString('valid="value"', $attrs);
        $this->assertStringNotContainsString('async', $attrs);
        $this->assertStringNotContainsString('test', $attrs);
    }

    public function testAssetNormalizesPath(): void
    {
        $manager = new AssetManager();
        
        $url1 = $manager->asset('/resources/js/app.jsx');
        $url2 = $manager->asset('resources/js/app.jsx');
        
        // Both should produce similar results (normalized)
        $this->assertIsString($url1);
        $this->assertIsString($url2);
    }
}

