<?php

namespace BareMetalPHP\Database\Seeder;

use RuntimeException;
class SeederRunner
{
    protected string $seederDirectory;

    public function __construct(string $path = null)
    {
        $this->seederDirectory = $path ?? base_path('database/seeders');
    }

    public function run(string $seederClass = 'DatabaseSeeder'): void
    {
        $file = $this->seederDirectory . '/' . $seederClass . '.php';

        if (! file_exists($file)) {
            throw new RuntimeException("Seeder file not found: {$file}");
        }

        require_once $file;

        $seeder = new $seederClass();

        if (! $seeder instanceof Seeder) {
            throw new RuntimeException("{$seederClass} must extend Seeder");
        }

        $seeder->run();
    }
}