<?php

declare(strict_types=1);

namespace BareMetalPHP\Providers;

use BareMetalPHP\Support\ServiceProvider;
use BareMetalPHP\View\View;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Set view base path (can be overridden by config)
        $basePath = $this->getViewBasePath();
        $cachePath = $this->getViewCachePath();

        View::setBasePath($basePath);
        View::setCachePath($cachePath);
    }

    protected function getViewBasePath(): string
    {
        // Check for explicit environment variable first
        if (getenv('VIEW_BASE_PATH')) {
            return getenv('VIEW_BASE_PATH');
        }

        // Try multiple locations for views (in order of priority)
        $possiblePaths = [
            // Current working directory (where script is run from) - highest priority
            getcwd() . '/resources/views',
            // Framework root (relative to this file) - for development/testing
            dirname(__DIR__, 2) . '/resources/views',
        ];
        
        // Return the first path that exists, or default to current working directory
        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }
        
        // Default to current working directory if nothing found
        return getcwd() . '/resources/views';
    }

    protected function getViewCachePath(): string
    {
        // Check for explicit environment variable first
        if (getenv('VIEW_CACHE_PATH')) {
            return getenv('VIEW_CACHE_PATH');
        }

        // Try multiple locations for view cache (in order of priority)
        $possiblePaths = [
            // Current working directory (where script is run from) - highest priority
            getcwd() . '/storage/views',
            // Framework root (relative to this file) - for development/testing
            dirname(__DIR__, 2) . '/storage/views',
        ];
        
        // Return the first path that exists, or default to current working directory
        foreach ($possiblePaths as $path) {
            // Create directory if it doesn't exist (for cache)
            if (!is_dir($path)) {
                if (is_dir(dirname($path))) {
                    @mkdir($path, 0755, true);
                }
            }
            if (is_dir($path)) {
                return $path;
            }
        }
        
        // Default to current working directory if nothing found
        $defaultPath = getcwd() . '/storage/views';
        @mkdir($defaultPath, 0755, true);
        return $defaultPath;
    }
}

