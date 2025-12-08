<?php

declare(strict_types=1);

namespace BareMetalPHP\Support\Facades;

use BareMetalPHP\Support\Log as LogSupport;

/**
 * @method static void info(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 */
class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LogSupport::class;
    }
}
