<?php
namespace Framework\Console;

class Application
{
    protected array $commands = [];

    public function __construct()
    {
        $this->commands = [
            'serve' => new ServeCommand(),
            'make:controller' => new MakeControllerCommand(),
            'migrate' => new MigrateCommand(),
            'migrate:rollback' => new MigrateRollbackCommand(),
            'make:migration' => new MakeMigrationCommand(),
            'frontend:install' => new Commands\InstallFrontendCommand(),
        ];

    }

    public function run(array $argv): void
    {
        $commandName = $argv[1] ?? null;

        if (!$commandName || !isset($this->commands[$commandName])) {
            $this->displayAvailableCommands();
            return;
        }

        $command = $this->commands[$commandName];

        $args = array_slice($argv, 2);
        
        // all commands acept handle(array $args = [])
        $command->handle($args);
    }

    public function displayAvailableCommands(): void
    {
        echo "Available commands:\n";
        foreach ($this->commands as $name => $cmd) {
            echo " $name\n";
        }
    }
}