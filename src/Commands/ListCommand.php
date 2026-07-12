<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;

#[AsCommand(name: 'podman:list')]
class ListCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:list
        {--filter= : Filter output based on conditions give}
        {--format= : Format the output}
        {--noheading : Do not print column headings}
    ';

    public $description = 'List all Quadlets configured for the current user.';

    public function handle(): int
    {
        $process = $this->listPodmanQuadlet(
            filter: $this->option('filter'),
            format: $this->option('format'),
            noheading: $this->option('noheading'),
        );

        $process->run();

        if (! $process->isSuccessful()) {
            error($process->getErrorOutput());

            return self::FAILURE;
        }

        $this->line($process->getOutput());

        return self::SUCCESS;
    }
}
