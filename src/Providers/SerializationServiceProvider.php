<?php

declare(strict_types=1);

namespace BareMetalPHP\Providers;

use BareMetalPHP\Support\ServiceProvider;
use BareMetalPHP\Serialization\Serializer;
use BareMetalPHP\Serialization\JsonEncoder;
use BareMetalPHP\Serialization\DefaultNormalizer;

class SerializationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Serializer::class, function () {
            return new Serializer(
                normalizers: [
                    new DefaultNormalizer(),
                ],
                encoders: [
                    new JsonEncoder(),
                ]
            );
        });
    }
}