<?php

namespace BareMetalPHP\Database\Seeder;

use BareMetalPHP\Application;
abstract class Seeder
{
    abstract public function run(): void;

    protected function call(string $seederClass, ?string $seederDirectory = null): void
    {
        // If class doesn't exist, try to load it via SeederRunner
        if (!class_exists($seederClass)) {
            $seederDirectory = $seederDirectory ?? base_path('database/seeders');
            $runner = new SeederRunner($seederDirectory);
            $runner->run($seederClass);
            return;
        }
        
        $seeder = new $seederClass();
        if (!$seeder instanceof Seeder) {
            throw new \RuntimeException("{$seederClass} must extend Seeder");
        }
        $seeder->run();
    }
}