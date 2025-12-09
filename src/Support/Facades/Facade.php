<?php

declare(strict_types=1);

namespace BareMetalPHP\Support\Facades;

use BareMetalPHP\Application;

/**
 * Base class for all facades.
 * 
 * Facades provide a "static" interface to objects managed by the 
 * application container. Concrete facades only need to implement
 * getFacadeAccessor() to tell the base class what to resolve.
 * /
 */
abstract class Facade
{
    /**
     * The application instance being used by the facades.
     */
    protected static ?Application $app = null;

    /**
     * Resolved instances keyed by accessor.
     * 
     * @var array<string, object>
     */
    protected static array $resolvedInstances = [];

    /**
     * Get the registered name of the component.
     * 
     * @return string
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Set the application instance to be used by facades.
     * @param Application|null $app
     * @return void
     */
    public static function setFacadeApplication(?Application $app): void
    {
        static::$app = $app;
    }

    /**
     * Get the root object behind the facade.
     * @return Application|null
     */
    public static function getFacadeApplication(): ?Application
    {
        if (static::$app !== null) {
            return static::$app;
        }

        return Application::getInstance();
    }

    protected static function resolveFacadeInstance(string|object $name): object
    {
        // If we are given a concrete object, just return it
        if (is_object($name)) {
            return $name;
        }

        if (isset(static::$resolvedInstances[$name])) {
            return static::$resolvedInstances[$name];
        }
        
        $app = static::getFacadeApplication();

        if (! $app) {
            throw new \RuntimeException(
                'Application instance is not available for facade: ' . static::class
            );
        }

        // Let the container resolve the underlying instance.
        $instance = $app->make($name);

        return static::$resolvedInstances[$name] = $instance;
    }
    /**
     * Clear a single resolved facade instance.
     * @param string $name
     * @return void
     */
    public static function clearResolvedInstance(string $name): void
    {
        unset(static::$resolvedInstances[$name]);
    }

    /**
     * Clear all resolved facade instances.
     * @return void
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstances = [];
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new \RuntimeException('Facade root not set for: ' . static::class);
        }

        return $instance->$method(...$args);
    }
    
    protected static function getFacadeRoot(): ?object
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }
}