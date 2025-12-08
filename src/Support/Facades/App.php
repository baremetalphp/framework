<?php

declare(strict_types=1);

namespace BareMetalPHP\Support\Facades;

use BareMetalPHP\Application;

class App extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        // We bypass the container here and just return the application instance.
        // By returning the class-string, we can resolve it via Application::getInstance().
        // The base Facade resolves via Application::make(), so we override getFacadeRoot instead.
        return Application::class;
    }

    public static function getFacadeRoot(): object
    {
        $app = static::getFacadeApplication();

        if (! $app) {
            throw new \RuntimeException('Application instance is not available for App facade.');
        }

        return $app;
    }
}