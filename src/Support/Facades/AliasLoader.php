<?php

declare(strict_types=1);

namespace BareMetalPHP\Support\Facades;

/**
 * Registers simple class aliases for facades, like:
 * 
 *     'Route' => \BareMetalPHP\Support\Facades\Route::class
 */
class AliasLoader
{
    /**
     * Singleton instance.
     * @var AliasLoader
     */
    protected static ?AliasLoader $instance = null;

    /**
     * Map of alias => fully-qualified class name.
     * 
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Whether the loader has registered its aliases. 
     * @var bool
     */
    protected bool $registered = false;

    protected function __construct(array $aliases)
    {
        $this->aliases = $aliases;
    }

    /**
     * Get (or create) the singleton instance.
     * @param array $aliases
     * @return AliasLoader|null
     */
    public static function getInstance(array $aliases = []): self
    {
        if (static::$instance === null) {
            static::$instance = new self($aliases);
        } elseif($aliases) {
            static::$instance->addAliases($aliases);
        }

        return static::$instance;
    }

    /**
     * Register all aliases with PHP via class_alias().
     * @return void
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        foreach ($this->aliases as $alias => $class) {
            if (! class_exists($alias) && class_exists($class)) {
                class_alias($class, $alias);
            }
        }
    }

    /**
     * Add aliases to the loader.
     * @param array $aliases
     * @return void
     */
    public function addAliases(array $aliases): void
    {
        $this->aliases = array_merge($this->aliases, $aliases);
    }

    /**
     * Get the currently registered aliases.
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

}