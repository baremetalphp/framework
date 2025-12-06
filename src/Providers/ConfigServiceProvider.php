<?php

declare(strict_types=1);

namespace Framework\Providers;

use Framework\Support\Config;
use Framework\Support\Env;
use Framework\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Load .env file first (before config)
        $this->loadEnvironmentFile();
        
        // Set config path (can be overridden via environment)
        $configPath = $this->getConfigPath();
        Config::setConfigPath($configPath);
    }

    public function boot(): void
    {
        // Load configuration files
        Config::load();
    }

    protected function loadEnvironmentFile(): void
    {
        // Try multiple locations for .env file (in order of priority)
        $possiblePaths = [
            // Current working directory (where script is run from) - highest priority
            getcwd() . '/.env',
            // Framework root (relative to this file)
            dirname(__DIR__, 2) . '/.env',
        ];
        
        // Only load the first .env file found
        foreach ($possiblePaths as $envPath) {
            if (file_exists($envPath)) {
                Env::load($envPath);
                break;
            }
        }
        
        // Also check for .env.local in the same locations (can override .env)
        $possibleLocalPaths = [
            getcwd() . '/.env.local',
            dirname(__DIR__, 2) . '/.env.local',
        ];
        
        foreach ($possibleLocalPaths as $envLocalPath) {
            if (file_exists($envLocalPath)) {
                // .env.local can override .env values
                Env::load($envLocalPath, true);
            }
        }
    }

    protected function getConfigPath(): string
    {
        $frameworkRoot = dirname(__DIR__, 2);
        return Env::get('CONFIG_PATH') ?: $frameworkRoot . '/config';
    }
}

