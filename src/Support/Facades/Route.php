<?php

declare(strict_types=1);

namespace BareMetalPHP\Support\Facades;

use BareMetalPHP\Routing\Router;

/**
 * @method static \BareMetalPHP\Routing\Route get(string $uri, callable|array|string $action)
 * @method static \BareMetalPHP\Routing\Route post(string $uri, callable|array|string $action)
 * @method static \BareMetalPHP\Routing\Route put(string $uri, callable|array|string $action)
 * @method static \BareMetalPHP\Routing\Route delete(string $uri, callable|array|string $action)
 * @method static \BareMetalPHP\Routing\Route any(string $uri, callable|array|string $action)
 * @method static string route(string $name, array $params = [])
 */
class Route extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Router::class;
    }
}