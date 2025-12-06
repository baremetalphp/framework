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
        $frameworkRoot = dirname(__DIR__, 2);
        return getenv('LOG_PATH') ?: $frameworkRoot . '/storage/logs';
    }
}

