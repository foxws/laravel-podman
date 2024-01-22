<?php

namespace Foxws\LaravelPodman\Commands;

use Illuminate\Console\Command;

class PublishCommand extends Command
{
    /**
     * @var string
     */
    public $signature = 'podman:publish';

    /**
     * @var string
     */
    protected $description = 'Publish the Docker files';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
