<?php

declare(strict_types=1);

namespace BareMetalPHP\Support\Facades;

use BareMetalPHP\Support\Session as SessionSupport;

/**
 * @method static void start()
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static void flash(string $key, mixed $value)
 * @method static void remove(string $key)
 * @method static void destroy()
 * @method static void regenerate()
 */
class Session extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        // If you later bind SessionSupport::class into the container, the base
        // Facade will resolve that instance. For now, we can just treat the
        // accessor as the class-string and bind it later if needed.
        return SessionSupport::class;
    }
}
