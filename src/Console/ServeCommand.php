<?php

namespace Framework\Console;

class ServeCommand
{
    public function handle(array $args = []): void
    {
        $host = '127.0.0.1';
        $port = 9010;

        $docRoot = realpath(__DIR__ .'/../../public');
        echo "Mini framework development server started:\n";
        echo "http://$host:$port\n";

        // equivalent to laravel's internal behavior
        $command = sprintf(
            'php -S %s:%d -t %s',
            $host,
            $port,
            $docRoot
        );
        

        passthru($command);
    }
}