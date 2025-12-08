<?php

namespace BareMetalPHP\Console\Commands;

class GoServeCommand
{
    public function handle(array $args = []): void
    {
        $config = config('appserver');

        if (empty($config['enabled'])) {
            echo "Go app server is disabled. Enable it in config/appserver.php or via APPSERVER_ENABLED=true.\n";
            return;
        }

        $dryRun = in_array('--dry-run', $args, true);

        $cmd = ['go', 'run', './cmd/server'];

        if ($dryRun) {
            echo "Go app server (dry run): would execute `" . implode(' ', $cmd) . "`\n";
            return;
        }

        echo "Starting Go app server...\n";
        echo "Press Ctrl+C to stop.\n";

        $process = proc_open(
            $cmd,
            [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ],
            $pipes,
            getcwd(),
        );

        if (!is_resource($process)) {
            echo "Failed to start Go app server process.\n";
            return;
        }

        proc_close($process);
    }
}