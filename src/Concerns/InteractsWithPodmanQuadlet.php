<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Foxws\Podman\Support\PodmanQuadletFile;
use Foxws\Podman\Support\PodmanQuadletPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use SplFileInfo;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

trait InteractsWithPodmanQuadlet
{
    protected ?TemporaryDirectory $podmanQuadletTemporaryDirectory = null;

    protected function podmanQuadletPath(): PodmanQuadletPath
    {
        return new PodmanQuadletPath;
    }

    protected function podmanQuadletFile(): PodmanQuadletFile
    {
        return new PodmanQuadletFile($this->podmanQuadletPath());
    }

    /**
     * A directory that is deleted once this command instance is destroyed,
     * used to materialize quadlet files the podman binary reads from disk.
     */
    protected function podmanQuadletTemporaryDirectory(): TemporaryDirectory
    {
        return $this->podmanQuadletTemporaryDirectory ??= TemporaryDirectory::make()->deleteWhenDestroyed();
    }

    protected function installPodmanQuadlet(
        string $service,
        ?string $application = null,
        ?bool $replace = null,
    ): Process {
        $path = $this->podmanQuadletPath();
        $source = "{$path->quadletsPath()}/{$service}.quadlets";
        $target = $this->podmanQuadletTemporaryDirectory()->path("{$service}.quadlets");

        $command = ['podman', 'quadlet', 'install'];

        if ($application) {
            $command[] = '--application';
            $command[] = $application;
        }

        if ($replace) {
            $command[] = '--replace';
        }

        if (! $path->shouldReloadSystemd()) {
            $command[] = '--reload-systemd=false';
        }

        $command[] = $this->podmanQuadletFile()->prepareSource($source, $target);

        return new Process($command);
    }

    protected function removePodmanQuadlet(
        string $service,
        ?bool $ignore = null,
        ?bool $force = null,
    ): Process {
        $command = ['podman', 'quadlet', 'rm', $service];

        if ($ignore) {
            $command[] = '--ignore';
        }

        if ($force) {
            $command[] = '--force';
        }

        if (! $this->podmanQuadletPath()->shouldReloadSystemd()) {
            $command[] = '--reload-systemd=false';
        }

        return new Process($command);
    }

    protected function listPodmanQuadlet(
        ?string $filter = null,
        ?string $format = null,
        ?bool $noheading = null,
    ): Process {
        $command = ['podman', 'quadlet', 'list'];

        if ($filter) {
            $command[] = '--filter';
            $command[] = $filter;
        }

        if ($format) {
            $command[] = '--format';
            $command[] = $format;
        }

        if ($noheading) {
            $command[] = '--noheading';
        }

        return new Process($command);
    }

    protected function printPodmanQuadlet(
        string $service,
    ): Process {
        $command = ['podman', 'quadlet', 'print', $service];

        return new Process($command);
    }

    protected function uninstallPodmanQuadlet(
        string $application,
        ?bool $force = null,
    ): Process {
        $command = ['podman', 'quadlet', 'rm', '--recursive', $application];

        if ($force) {
            $command[] = '--force';
        }

        if (! $this->podmanQuadletPath()->shouldReloadSystemd()) {
            $command[] = '--reload-systemd=false';
        }

        return new Process($command);
    }

    protected function setPodmanSecret(
        string $secret,
        string $value,
        ?bool $replace = null,
    ): Process {
        $command = ['podman', 'secret', 'create', $secret, '-'];

        if ($replace) {
            $command[] = '--replace';
        }

        $process = new Process($command);
        $process->setInput($value);

        return $process;
    }

    protected function publishPodmanRuntime(string $runtime, ?bool $force = null): bool
    {
        $source = "{$this->podmanQuadletPath()->runtimesPath()}/{$runtime}";
        $target = base_path('runtimes')."/{$runtime}";

        if (File::exists($target) && ! $force) {
            error("The runtime {$runtime} already exists at {$target}. Use --force to overwrite.");

            return false;
        }

        $this->podmanQuadletFile()->publishDirectory($source, $target);

        return true;
    }

    protected function promptForPodmanQuadletSecrets(string $service, ?bool $replace = null): bool
    {
        foreach ($this->getPodmanQuadletSecrets($service) as $secret => $definition) {
            if ($definition['type'] === 'mount') {
                $path = text(
                    label: "Enter the file path for {$secret} (".implode(', ', $definition['targets']).')',
                    default: base_path('.env'),
                    required: true,
                );

                if (! File::exists($path)) {
                    error("The file {$path} does not exist.");

                    return false;
                }

                $value = File::get($path);
            } else {
                $value = password(
                    label: "Enter the value for {$secret} (".implode(', ', $definition['targets']).')',
                    required: true,
                );
            }

            $process = $this->setPodmanSecret(
                secret: $secret,
                value: $value,
                replace: $replace,
            );

            $process->run();

            if (! $process->isSuccessful()) {
                error($process->getErrorOutput());

                return false;
            }
        }

        return true;
    }

    protected function getPodmanQuadlets(): array
    {
        return Collection::make(File::files($this->podmanQuadletPath()->quadletsPath()))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'quadlets')
            ->map(fn (SplFileInfo $file): string => $file->getBasename('.'.$file->getExtension()))
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $service): array => [$service => $service])
            ->toArray();
    }

    protected function getPodmanQuadletRuntimes(): array
    {
        return Collection::make(File::directories($this->podmanQuadletPath()->runtimesPath()))
            ->map(fn (string $path): string => basename($path))
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $runtime): array => [$runtime => $runtime])
            ->toArray();
    }

    protected function getPodmanQuadletSecrets(string $service): array
    {
        $source = "{$this->podmanQuadletPath()->quadletsPath()}/{$service}.quadlets";

        $contents = $this->podmanQuadletFile()->renderSource($source);

        $secrets = [];

        foreach (explode("\n", $contents) as $line) {
            if (! Str::startsWith($line, 'Secret=')) {
                continue;
            }

            $options = explode(',', Str::after($line, 'Secret='));
            $name = array_shift($options);
            $type = 'mount';
            $target = $name;

            foreach ($options as $option) {
                if (Str::startsWith($option, 'type=')) {
                    $type = Str::after($option, 'type=');
                }

                if (Str::startsWith($option, 'target=')) {
                    $target = Str::after($option, 'target=');
                }
            }

            $secrets[$name]['type'] = $type;
            $secrets[$name]['targets'][] = $target;
        }

        return $secrets;
    }

    protected function getPodmanRuntimePath(): string
    {
        return $this->podmanQuadletPath()->runtimePath();
    }
}
