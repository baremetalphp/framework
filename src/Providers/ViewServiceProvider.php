<?php

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Support\ServiceProvider;
use Framework\View\View;

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
        $frameworkRoot = dirname(__DIR__, 2);
        return getenv('VIEW_BASE_PATH') ?: $frameworkRoot . '/resources/views';
    }

    protected function getViewCachePath(): string
    {
        $frameworkRoot = dirname(__DIR__, 2);
        return getenv('VIEW_CACHE_PATH') ?: $frameworkRoot . '/storage/views';
    }
}

