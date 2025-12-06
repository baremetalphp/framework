<?php

declare(strict_types=1);

namespace Tests\Feature;

use Framework\Application;
use Framework\Http\Kernel;
use Framework\Routing\Router;

/**
 * Test kernel without middleware to avoid output during tests
 */
class TestKernel extends Kernel
{
    protected array $middleware = [];
}

