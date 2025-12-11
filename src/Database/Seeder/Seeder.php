<?php

namespace BareMetalPHP\Database\Seeder;

use BareMetalPHP\Application;
abstract class Seeder
{
    abstract public function run(): void;

    protected function call(string $seederClass): void
    {
        (new $seederClass())->run();
    }
}