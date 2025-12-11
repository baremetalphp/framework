<?php

declare(strict_types= 1);

namespace BareMetalPHP\Config;

class Repository
{
    /**
     * @var array<string, mixed>
     */
    protected array $items = [];

    /**
     * @param array<string, mixed> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Load config files from a directory.
     * 
     * @param string $configPath
     * @return Repository
     */
    public static function fromPath(string $configPath): self
    {
        $items = [];

        foreach (glob(rtrim($configPath, "/"). '/*.php') as $file) {
            $name = basename($file, '.php'); // app.php -> "app"
            $items[$name] = require $file;
        }

        return new self($items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__missing__') != '__missing__';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);

        $value = $this->items;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);

        $array = $this->items;

        while (count($segments) > 1) {
            $segment = array_shift($segments);

            if (! isset($array[$segment]) || ! is_array($array[$segment])) {
                $array[$segment] = [];
            }

            $array =& $array[$segment];
        }

        $array[array_shift($segments)] = $value;
    }
}