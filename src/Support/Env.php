<?php

declare(strict_types=1);

namespace BareMetalPHP\Support;

final class Env
{
    /**
     * Track if environment has been loaded (for testing purposes)
     */
    protected static bool $loaded = false;

    /**
     * Load environment variables from a file.
     * 
     * @param string $path Path to .env file
     * @param bool $override If true, will override existing values. If false, will skip if already set.
     */
    public static function load(string $path, bool $override = false): void
    {
        if (! is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // KEY=VALUE
            [$name, $value] = array_pad(explode('=', $line, 2), 2, null);

            $name = trim($name);
            $value = trim((string) $value);

            // strip quotes
            $value = trim($value, "\"'");

            // Only set if override is true, or if the variable doesn't already exist
            if ($override || !array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv("{$name}={$value}");
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = null;
        
        if (array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        } else {
            $envValue = getenv($key);
            if ($envValue !== false) {
                $value = $envValue;
            }
        }

        if ($value === null) {
            return $default;
        }

        // Auto-cast string values to common types
        // If value is already a non-string type, return as-is
        if (!is_string($value)) {
            return $value;
        }

        return self::castValue($value);
    }

    /**
     * Cast string value to appropriate type (bool, int, float, array)
     */
    protected static function castValue(string $value): mixed
    {
        // Handle boolean values
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        
        // Handle integers
        if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
            return (int) $value;
        }
        
        // Handle floats
        if (is_numeric($value) && str_contains($value, '.')) {
            return (float) $value;
        }
        
        // Handle arrays (comma-separated or JSON-like)
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            // Try to parse as array: [item1,item2,item3]
            $content = trim($value, '[]');
            if ($content !== '') {
                $items = array_map('trim', explode(',', $content));
                return $items;
            }
            return [];
        }
        
        // Return as string by default
        return $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_ENV) || getenv($key) !== false;
    }
}