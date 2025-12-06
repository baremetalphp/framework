<?php

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Application;
use Framework\Routing\Router;
use Framework\Support\ServiceProvider;

class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Router::class, function (Application $app) {
            $router = new Router($app);

            // Load routes from web.php by default
            $routesFile = $this->getRoutesFile();
            
            if (file_exists($routesFile)) {
                $define = require $routesFile;
                $define($router);
            }

            return $router;
        });
    }

    protected function getRoutesFile(): string
    {
        $frameworkRoot = dirname(__DIR__, 2);
        return getenv('ROUTES_FILE') ?: $frameworkRoot . '/routes/web.php';
    }
}

