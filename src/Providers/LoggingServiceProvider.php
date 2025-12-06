<?php

declare(strict_types=1);

namespace BareMetalPHP\Providers;

use BareMetalPHP\Support\Log;
use BareMetalPHP\Support\ServiceProvider;

class LoggingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Set log path (can be overridden via environment)
        $logPath = $this->getLogPath();
        Log::setLogPath($logPath);
    }

    protected function getLogPath(): string
    {
        // Check for explicit environment variable first
        if (getenv('LOG_PATH')) {
            return getenv('LOG_PATH');
        }

        // Try multiple locations for logs (in order of priority)
        $possiblePaths = [
            // Current working directory (where script is run from) - highest priority
            getcwd() . '/storage/logs',
            // Framework root (relative to this file) - for development/testing
            dirname(__DIR__, 2) . '/storage/logs',
        ];
        
        // Return the first path that exists, or create and return current working directory
        foreach ($possiblePaths as $path) {
            // Create directory if it doesn't exist (for logs)
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
        $defaultPath = getcwd() . '/storage/logs';
        @mkdir($defaultPath, 0755, true);
        return $defaultPath;
    }
}

