<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: "sail:install")]
class InstallCommand extends Command
{
    public $signature = "podman:install
        {--service= : The service to install}
        {--application= : The application name, installed under a directory with this name}
        {--reload-systemd : Reload systemd after installation}
        {--replace : Replace the service if it already exists}
    ";

    public $description = "Install a service for the application to run in Podman";

    public function handle(): int
    {
        $this->comment("All done");

        return self::SUCCESS;
    }
}
