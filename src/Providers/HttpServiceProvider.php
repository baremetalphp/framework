<?php

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Application;
use Framework\Http\Kernel;
use Framework\Support\ServiceProvider;

class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Kernel::class, function (Application $app) {
            return new Kernel(
                $app,
                $app->make(\Framework\Routing\Router::class)
            );
        });
    }
}

