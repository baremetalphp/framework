<?php

namespace BareMetalPHP\Console\Commands;

class SeedDatabaseCommand
{
    public function handle(array $args = []): void
    {
        if ($argv[1] === 'seed') {
            $seeder = $argv[2] ?? 'DatabaseSeeder';

            $runner = new \BareMetalPHP\Database\Seeder\SeederRunner();
            $runner->run($seeder);

            echo "Seeded using {$seeder}\n";

            exit;
        }
    }
}