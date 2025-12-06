<?php

declare(strict_types=1);

namespace BareMetalPHP\Providers;

use BareMetalPHP\Application;
use BareMetalPHP\Routing\Router;
use BareMetalPHP\Support\ServiceProvider;

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
        // Check for explicit environment variable first
        if (getenv('ROUTES_FILE')) {
            return getenv('ROUTES_FILE');
        }

        // Try multiple locations for routes file (in order of priority)
        $possiblePaths = [
            // Current working directory (where script is run from) - highest priority
            getcwd() . '/routes/web.php',
            // Framework root (relative to this file) - for development/testing
            dirname(__DIR__, 2) . '/routes/web.php',
        ];
        
        // Return the first path that exists, or default to current working directory
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Default to current working directory if nothing found (will be checked in register())
        return getcwd() . '/routes/web.php';
    }
}

