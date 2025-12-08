<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Support\Facades\AliasLoader;
use Tests\TestCase;

class AliasLoaderTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset singleton
        $reflection = new \ReflectionClass(AliasLoader::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
        
        parent::tearDown();
    }

    public function testGetInstanceCreatesSingleton(): void
    {
        $loader1 = AliasLoader::getInstance(['Test' => \stdClass::class]);
        $loader2 = AliasLoader::getInstance();
        
        $this->assertSame($loader1, $loader2);
    }

    public function testGetInstanceAddsAliasesWhenProvided(): void
    {
        $loader1 = AliasLoader::getInstance(['Test1' => \stdClass::class]);
        $loader2 = AliasLoader::getInstance(['Test2' => \stdClass::class]);
        
        $aliases = $loader2->getAliases();
        
        $this->assertArrayHasKey('Test1', $aliases);
        $this->assertArrayHasKey('Test2', $aliases);
    }

    public function testRegisterCreatesClassAliases(): void
    {
        $loader = AliasLoader::getInstance([
            'TestAlias' => \stdClass::class
        ]);
        
        $loader->register();
        
        $this->assertTrue(class_exists('TestAlias'));
        $this->assertSame(\stdClass::class, get_class(new \TestAlias()));
    }

    public function testRegisterIsIdempotent(): void
    {
        $loader = AliasLoader::getInstance([
            'TestAlias' => \stdClass::class
        ]);
        
        $loader->register();
        $loader->register(); // Should not create duplicate aliases
        
        $this->assertTrue(class_exists('TestAlias'));
    }

    public function testRegisterSkipsExistingClasses(): void
    {
        // Create a real class first
        if (!class_exists('ExistingClass')) {
            eval('class ExistingClass {}');
        }
        
        $loader = AliasLoader::getInstance([
            'ExistingClass' => \stdClass::class
        ]);
        
        $loader->register();
        
        // Should not override existing class
        $this->assertNotSame(\stdClass::class, get_class(new \ExistingClass()));
    }

    public function testAddAliasesMergesWithExisting(): void
    {
        $loader = AliasLoader::getInstance(['Test1' => \stdClass::class]);
        $loader->addAliases(['Test2' => \stdClass::class]);
        
        $aliases = $loader->getAliases();
        
        $this->assertArrayHasKey('Test1', $aliases);
        $this->assertArrayHasKey('Test2', $aliases);
    }

    public function testGetAliasesReturnsAllAliases(): void
    {
        $aliases = [
            'Test1' => \stdClass::class,
            'Test2' => \Exception::class
        ];
        
        $loader = AliasLoader::getInstance($aliases);
        
        $this->assertSame($aliases, $loader->getAliases());
    }
}
