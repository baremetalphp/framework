<?php

namespace Framework\Console;

abstract class Command
{
    protected array $arguments = [];

    public function setArguments(array $args): void
    {
        $this->arguments = $args;
    }

    public function argument(int $index): ?string
    {
        return $this->arguments[$index] ?? null;
    }

    abstract public function handle(): void;
}
