<?php

declare(strict_types=1);

namespace BareMetalPHP\Support\Facades;

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\ConnectionManager;

class DB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        // you can choose either Connection::class or ConnectionManager::class depending
        // on your preference.
        return Connection::class;
    }

    public static function connection(?string $name = null): Connection
    {
        /**
         * @var ConnectionManager $manager
         */
        $manager = static::resolveFacadeInstance(ConnectionManager::class);

        return $manager->connection($name);
    }
}