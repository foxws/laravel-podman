<?php

namespace Foxws\LaravelPodman\Commands;

use Illuminate\Console\Command;

class LaravelPodmanCommand extends Command
{
    public $signature = 'laravel-podman';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
