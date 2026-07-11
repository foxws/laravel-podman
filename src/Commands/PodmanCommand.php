<?php

namespace Foxws\Podman\Commands;

use Illuminate\Console\Command;

class PodmanCommand extends Command
{
    public $signature = 'laravel-podman';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
