<?php

declare(strict_types=1);

use Framework\Container\Container;
use Framework\Database\Schema\SchemaBuilder;

if (!function_exists('dd')) {
    /**
     * Dump and die - useful for debugging
     */
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die(1);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump variables without dying
     */
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
    }
}

if (!function_exists('abort')) {
    /**
     * Abort request with status code and optional message
     */
    function abort(int $code = 500, string $message = ''): never
    {
        http_response_code($code);
        
        if ($message) {
            echo $message;
        } else {
            $messages = [
                404 => 'Not Found',
                403 => 'Forbidden',
                500 => 'Internal Server Error',
            ];
            echo $messages[$code] ?? 'Error';
        }
        
        die(1);
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to a URL
     */
    function redirect(string $url, int $statusCode = 302): \Framework\Http\Response
    {
        return new \Framework\Http\Response('', $statusCode, ['Location' => $url]);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value using dot notation
     */
    function config(string $key, mixed $default = null): mixed
    {
        return \Framework\Support\Config::get($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable with optional default and type casting
     */
    function env(string $key, mixed $default = null): mixed
    {
        return \Framework\Support\Env::get($key, $default);
    }
}

if (!function_exists('log_info')) {
    /**
     * Log an info message
     */
    function log_info(string $message, array $context = []): void
    {
        \Framework\Support\Log::info($message, $context);
    }
}

if (!function_exists('log_error')) {
    /**
     * Log an error message
     */
    function log_error(string $message, array $context = []): void
    {
        \Framework\Support\Log::error($message, $context);
    }
}

if (!function_exists('log_warning')) {
    /**
     * Log a warning message
     */
    function log_warning(string $message, array $context = []): void
    {
        \Framework\Support\Log::warning($message, $context);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset(string $path): string
    {
        // Remove leading slash if present
        $path = ltrim($path, '/');
        
        // Get base URL from environment or default to /
        $baseUrl = env('APP_URL', '/');
        $baseUrl = rtrim($baseUrl, '/');
        
        return $baseUrl . '/' . $path;
    }
}

if (!function_exists('url')) {
    /**
     * Generate full URL
     */
    function url(?string $path = null): string
    {
        $baseUrl = env('APP_URL', 'http://localhost');
        $baseUrl = rtrim($baseUrl, '/');
        
        if ($path === null) {
            return $baseUrl;
        }
        
        $path = ltrim($path, '/');
        return $baseUrl . '/' . $path;
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value (for forms with validation errors)
     * Note: Requires session/flash message implementation to work fully
     */
    function old(?string $key = null, mixed $default = null): mixed
    {
        // TODO: Implement with session flash messages
        // For now, return default
        return $default;
    }
}

if (!function_exists('route')) {
    /**
     * Generate route URL by name
     */
    function route(string $name, array $parameters = []): string
    {
        $app = \Framework\Application::getInstance();
        
        if (!$app) {
            throw new \RuntimeException('Application instance not available. Make sure bootstrap/app.php sets Application::setInstance($app).');
        }
        
        $router = $app->make(\Framework\Routing\Router::class);
        $path = $router->route($name, $parameters);
        
        // Return relative path - use url() helper if you need full URL
        return $path;
    }
}

if (!function_exists('view')) {
    /**
     * Create a view response
     */
    function view(string $view, array $data = []): \Framework\Http\Response
    {
        return \Framework\View\View::make($view, $data);
    }
}

if (!function_exists('response')) {
    /**
     * Create a response
     */
    function response(string $content = '', int $statusCode = 200, array $headers = []): \Framework\Http\Response
    {
        return new \Framework\Http\Response($content, $statusCode, $headers);
    }
}

if (!function_exists('json_response')) {
    /**
     * Create a JSON response
     */
    function json_response(array $data, int $statusCode = 200): \Framework\Http\Response
    {
        return new \Framework\Http\Response(
            json_encode($data, JSON_PRETTY_PRINT),
            $statusCode,
            ['Content-Type' => 'application/json']
        );
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the base path of the application
     */
    function base_path(?string $path = null): string
    {
        $basePath = dirname(__DIR__, 2);
        
        if ($path === null) {
            return $basePath;
        }
        
        return $basePath . '/' . ltrim($path, '/');
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the base path of the application
     */
    function base_path(?string $path = null): string
    {
        $frameworkRoot = dirname(__DIR__, 2);
        
        if ($path === null) {
            return $frameworkRoot;
        }
        
        return $frameworkRoot . '/' . ltrim($path, '/');
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the path to the database directory
     */
    function database_path(?string $path = null): string
    {
        return base_path($path ? 'database/' . ltrim($path, '/') : 'database');
    }
}

if (!function_exists('vite')) {
    /**
     * Get Vite asset manager instance
     */
    function vite(): ?\Framework\Frontend\AssetManager
    {
        $app = \Framework\Application::getInstance();
        if ($app) {
            try {
                return $app->make(\Framework\Frontend\AssetManager::class);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }
}

if (!function_exists('spa')) {
    /**
     * Render an SPA response
     */
    function spa(string $component, array $props = [], ?string $layout = null): \Framework\Http\Response
    {
        $app = \Framework\Application::getInstance();
        if ($app) {
            try {
                $helper = $app->make(\Framework\Frontend\SPAHelper::class);
                return $helper->render($component, $props, $layout);
            } catch (\Exception $e) {
                throw new \RuntimeException('SPA helper not available. Make sure FrontendServiceProvider is registered.');
            }
        }
        throw new \RuntimeException('Application instance not available');
    }
}

if (!function_exists('auth')) {
    /**
     * Get the authenticated user or check authentication
     */
    function auth(): ?\App\Models\User
    {
        return \Framework\Auth\Auth::user();
    }
}

if (!function_exists('session')) {
    /**
     * Get or set a session value
     */
    function session(?string $key = null, mixed $value = null): mixed
    {
        if ($key === null) {
            return $_SESSION ?? [];
        }

        if ($value === null) {
            return \Framework\Support\Session::get($key);
        }

        \Framework\Support\Session::set($key, $value);
        return $value;
    }
}

