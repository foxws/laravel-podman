<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Foxws\Podman\Support\PodmanQuadletFile;
use Foxws\Podman\Support\PodmanQuadletPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

trait InteractsWithPodmanQuadlet
{
    protected function podmanQuadletPath(): PodmanQuadletPath
    {
        return new PodmanQuadletPath;
    }

    protected function podmanQuadletFile(): PodmanQuadletFile
    {
        return new PodmanQuadletFile($this->podmanQuadletPath());
    }

    protected function installPodmanQuadlet(
        string $service,
        ?string $application = null,
        ?bool $replace = null,
    ): Process {
        $command = $this->podmanQuadletInstallCommand(
            target: $this->publishPodmanQuadlet($service),
            application: $application,
            replace: $replace,
        );

        return new Process($command);
    }

    /**
     * @return array<int, string>
     */
    protected function podmanQuadletInstallCommand(
        string $target,
        ?string $application = null,
        ?bool $replace = null,
    ): array {
        $command = ['podman', 'quadlet', 'install'];

        if ($application) {
            $command[] = '--application';
            $command[] = $application;
        }

        if ($replace) {
            $command[] = '--replace';
        }

        if (! $this->podmanQuadletPath()->shouldReloadSystemd()) {
            $command[] = '--reload-systemd=false';
        }

        $command[] = $target;

        return $command;
    }

    /**
     * Whether the "podman" binary can be found on the PATH. Used to skip
     * actually installing a rendered quadlet when Artisan runs somewhere
     * podman itself isn't available (e.g. inside a plain PHP container).
     */
    protected function podmanBinaryAvailable(): bool
    {
        return (new ExecutableFinder)->find('podman') !== null;
    }

    /**
     * Render a service's ".quadlets" file and write it to the configured
     * publish path. Every install goes through this first, so the rendered
     * file used to install a service is always available for inspection at
     * that path afterwards, install or not.
     */
    protected function publishPodmanQuadlet(string $service): string
    {
        $path = $this->podmanQuadletPath();
        $source = "{$path->quadletsPath()}/{$service}.quadlets";
        $target = "{$path->publishPath()}/{$service}.quadlets";

        return $this->podmanQuadletFile()->prepareSource($source, $target);
    }

    /**
     * Install one or more services, prompting for and setting their secrets
     * first when requested. Returns the names of the services that failed.
     *
     * Every service is rendered to the configured publish path regardless.
     * Actually installing it is skipped when $install is false or the
     * "podman" binary isn't available — secrets are skipped in that case
     * too, since setting them also requires "podman". Run
     * "podman quadlet install" against the rendered file on the host
     * afterwards to finish installing it.
     *
     * @param  array<int, string>  $services
     * @return array<int, string>
     */
    protected function installPodmanQuadlets(
        array $services,
        ?string $application = null,
        ?bool $replace = null,
        ?bool $secrets = null,
        ?bool $install = null,
    ): array {
        $failed = [];
        $install = ($install ?? true) && $this->podmanBinaryAvailable();

        foreach ($services as $service) {
            if ($install && $secrets && ! $this->promptForPodmanQuadletSecrets($service, replace: $replace)) {
                $failed[] = $service;

                continue;
            }

            $process = $this->installPodmanQuadlet(
                service: $service,
                application: $application,
                replace: $replace,
            );

            if (! $install) {
                $target = "{$this->podmanQuadletPath()->publishPath()}/{$service}.quadlets";

                info("Service {$service} prepared at {$target}. Run \"{$process->getCommandLine()}\" on the host to finish installing it.");

                continue;
            }

            $process->run();

            if (! $process->isSuccessful()) {
                error($process->getErrorOutput());

                $failed[] = $service;

                continue;
            }

            info("Service {$service} installed successfully.");
        }

        return $failed;
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
        $target = "{$this->podmanQuadletPath()->runtimePath()}/{$runtime}";

        if (File::exists($target) && ! $force) {
            error("The runtime {$runtime} already exists at {$target}. Use --force to overwrite.");

            return false;
        }

        $this->podmanQuadletFile()->publishDirectory($source, $target);

        return true;
    }

    /**
     * Publish one or more runtimes. Returns the names of the runtimes that failed.
     *
     * @param  array<int, string>  $runtimes
     * @return array<int, string>
     */
    protected function publishPodmanRuntimes(array $runtimes, ?bool $force = null): array
    {
        $failed = [];

        foreach ($runtimes as $runtime) {
            if (! $this->publishPodmanRuntime($runtime, force: $force)) {
                $failed[] = $runtime;

                continue;
            }

            info("Runtime {$runtime} published to {$this->getPodmanRuntimePath()}");
        }

        return $failed;
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

    protected function getPodmanQuadletDefaultServices(): array
    {
        return $this->podmanQuadletPath()->defaultServices();
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

    protected function getPodmanQuadletDefaultRuntimes(): array
    {
        return $this->podmanQuadletPath()->defaultRuntimes();
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
