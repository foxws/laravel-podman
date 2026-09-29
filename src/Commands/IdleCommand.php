<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Support\Idle\PodmanIdle;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;

#[AsCommand(name: 'podman:idle')]
class IdleCommand extends Command
{
    public $signature = 'podman:idle
        {--services= : Comma-separated checks to run, e.g. "queue,database" (default: every check the app uses)}
    ';

    public $description = 'Exit successfully when the app has no work in progress, so the idle check can stop its workers while it sleeps.';

    public function handle(PodmanIdle $idle): int
    {
        $option = $this->option('services');

        $services = array_values(array_filter(array_map(
            fn (string $service): string => trim($service),
            explode(',', is_string($option) ? $option : ''),
        )));

        $checks = $services === [] ? $idle->enabledChecks() : $idle->registeredChecks();

        if ($unknown = array_diff($services, array_keys($checks))) {
            error('Unknown idle checks: '.implode(', ', $unknown).'. Available: '.implode(', ', array_keys($idle->registeredChecks())).'.');

            return self::FAILURE;
        }

        if ($services !== []) {
            $checks = array_intersect_key($checks, array_flip($services));
        }

        $busy = 0;

        foreach ($checks as $check) {
            $result = $check->run();

            if (! $result->isIdle) {
                $busy++;

                $this->line("{$check->name()}: {$result->message}");
            }
        }

        return $busy === 0 ? self::SUCCESS : self::FAILURE;
    }
}
