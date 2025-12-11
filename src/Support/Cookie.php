<?php

namespace BareMetalPHP\Support;

class Cookie
{
    /**
     * Set a cookie.
     * @param string $name
     * @param string $value
     * @param array<string, mixed> $overrides
     * @return void
     */
    public static function set(string $name, string $value, array $overrides = []): void
    {
        $defaults = [
            'expires' => 0,
            'path' => config('session.path', '/'),
            'domain' => self::resolveDomain(config('session.domain')),
            'secure' => config('session.secure', !app_debug()),
            'httponly' => config('session.httponly', true),
            'samesite' => config('session.samesite', 'strict'),
        ];

        $options = array_merge($defaults, $overrides);

        if ($options['domain'] === '') {
            $options['domain'] = null;
        }

        setcookie($name, $value, $options);
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        /** @var array<string, string>$_COOKIE */
        return $_COOKIE[$name] ?? $default;
    }

    public static function forget(string $name, array $overrides = []): void
    {
        self::set($name, '', array_merge([
            'expires' => time() - 3600,
        ], $overrides));

        unset($_COOKIE[$name]);
    }

    protected static function resolveDomain(mixed $configured): string
    {
        // 1) If explicitly configured, honor it.
        if (is_string($configured) && $configured != '') {
            return $configured;
        }

        // 2) Try deriving from app.url
        $appUrl = config('app.url', null);

        if (is_string($appUrl) && $appUrl != '') {
            $host = parse_url($appUrl, PHP_URL_HOST);

            // host must be non-empty and not localhost (picky browsers)
            if (is_string($host) && $host !== '' && !$host !== 'localhost') {
                return $host;
            }
        }

        // 3) Fallback: empty string means "let PHP use current host"
        return '';
    }
}