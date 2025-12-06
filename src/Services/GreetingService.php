<?php

namespace Framework\Service;

class GreetingService
{
    public function greet(string $name): string
    {
        return "Hello, {$name}, from GreetingService!";
    }
}