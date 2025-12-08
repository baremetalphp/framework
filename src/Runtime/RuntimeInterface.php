<?php

declare(strict_types=1);

namespace BareMetalPHP\Runtime;

interface RuntimeInterface
{
    /**
     * Run the application for the current environment and return an exit code.
     * @param mixed $app Typically the BareMetalPHP\Application or Http\Kernel
     * @return int
     */
    public function run(mixed $app): int;
}