<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Composer\InstalledVersions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use SplFileInfo;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

trait InteractsWithPodmanQuadlet
{
    protected function installPodmanQuadlet(
        string $service,
        ?string $application = null,
        ?bool $replace = null,
    ): Process {
        $source = "{$this->getPodmanQuadletsPath()}/{$service}.quadlets";
        $target = "{$this->getPodmanTemporaryPath()}/{$service}.quadlets";

        $command = ['podman', 'quadlet', 'install'];

        if ($application) {
            $command[] = '--application';
            $command[] = $application;
        }

        if ($replace) {
            $command[] = '--replace';
        }

        if (! $this->shouldReloadSystemd()) {
            $command[] = '--reload-systemd=false';
        }

        $command[] = $this->preparePodmanQuadletSource($source, $target);

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

        if (! $this->shouldReloadSystemd()) {
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

        if (! $this->shouldReloadSystemd()) {
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
        $source = "{$this->getPodmanRuntimesPath()}/{$runtime}";
        $target = base_path('runtimes');

        if (File::exists($target) && ! $force) {
            error("The runtime {$runtime} already exists at {$target}. Use --force to overwrite.");

            return false;
        }

        $this->publishPodmanQuadletDirectory($source, $target);

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

    protected function getPodmanQuadletSubstitutions(): array
    {
        return [
            '{{app-env}}' => Config::string('app.env'),
            '{{app-name}}' => Config::string('app.name'),
            '{{app-url}}' => Config::string('app.url'),
            '{{app-host}}' => $this->getPodmanQuadletDomain(),
            '{{app-uid}}' => (string) $this->getPodmanQuadletUid(),
            '{{app-gid}}' => (string) $this->getPodmanQuadletGid(),
            '{{application}}' => $this->getPodmanQuadletPrefix(),
            '{{base-path}}' => base_path(),
            '{{config-path}}' => $this->getPodmanConfigPath(),
            '{{runtime-path}}' => $this->getPodmanRuntimePath(),
        ];
    }

    protected function publishPodmanQuadletDirectory(string $source, string $target): void
    {
        File::ensureDirectoryExists($target);

        foreach (File::allFiles($source) as $file) {
            $destination = "{$target}/{$file->getRelativePathname()}";

            $this->preparePodmanQuadletSource($file->getRealPath(), $destination);
        }
    }

    protected function preparePodmanQuadletSource(string $source, string $target): string
    {
        $contents = strtr(File::get($source), $this->getPodmanQuadletSubstitutions());

        if (! $this->shouldUseSelinuxVolumeMapping()) {
            $contents = $this->removeSelinuxVolumeFlags($contents);
        }

        File::ensureDirectoryExists(dirname($target));

        File::put($target, $contents);

        return $target;
    }

    protected function removeSelinuxVolumeFlags(string $contents): string
    {
        return preg_replace_callback(
            '/^Volume=(.*)$/m',
            function (array $matches): string {
                $segments = Str::of($matches[1])->explode(':');

                if ($segments->count() < 3) {
                    return "Volume={$matches[1]}";
                }

                $options = Str::of($segments->get(2))
                    ->explode(',')
                    ->diff(['Z', 'z', 'U']);

                $segments = $options->isEmpty()
                    ? $segments->take(2)
                    : $segments->put(2, $options->implode(','));

                return 'Volume='.$segments->implode(':');
            },
            $contents,
        );
    }

    protected function getPodmanVendorPath(): string
    {
        return Str::rtrim(
            InstalledVersions::getInstallPath('foxws/laravel-podman'),
            '/',
        );
    }

    protected function getPodmanQuadletDomain(): string
    {
        return Uri::of(Config::string('app.url'))->host();
    }

    protected function getPodmanQuadlets(): array
    {
        return Collection::make(File::files($this->getPodmanQuadletsPath()))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'quadlets')
            ->map(fn (SplFileInfo $file): string => $file->getBasename('.'.$file->getExtension()))
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $service): array => [$service => $service])
            ->toArray();
    }

    protected function getPodmanQuadletRuntimes(): array
    {
        return Collection::make(File::directories($this->getPodmanRuntimesPath()))
            ->map(fn (string $path): string => basename($path))
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $runtime): array => [$runtime => $runtime])
            ->toArray();
    }

    protected function getPodmanQuadletSecrets(string $service): array
    {
        $source = "{$this->getPodmanQuadletsPath()}/{$service}.quadlets";
        $target = "{$this->getPodmanTemporaryPath()}/{$service}.quadlets";

        $path = $this->preparePodmanQuadletSource($source, $target);

        $secrets = [];

        foreach (explode("\n", File::get($path)) as $line) {
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

    protected function getPodmanQuadletPrefix(): string
    {
        return Str::kebab(Config::string('podman.quadlet_prefix'));
    }

    protected function getPodmanQuadletUid(): int
    {
        $uid = Config::get('podman.quadlet_uid');

        if ($uid !== null) {
            return (int) $uid;
        }

        return function_exists('posix_getuid') ? posix_getuid() : 1000;
    }

    protected function getPodmanQuadletGid(): int
    {
        $gid = Config::get('podman.quadlet_gid');

        if ($gid !== null) {
            return (int) $gid;
        }

        return function_exists('posix_getgid') ? posix_getgid() : 1000;
    }

    protected function getPodmanQuadletsPath(): string
    {
        $path = Config::get('podman.quadlets_path');

        if ($path && File::isDirectory($path)) {
            return $path;
        }

        return "{$this->getPodmanVendorPath()}/quadlets";
    }

    protected function getPodmanRuntimesPath(): string
    {
        $path = Config::get('podman.runtimes_path');

        if ($path && File::isDirectory($path)) {
            return $path;
        }

        return "{$this->getPodmanVendorPath()}/runtimes";
    }

    protected function getPodmanRuntimePath(): string
    {
        return Config::get('podman.runtime_path');
    }

    protected function getPodmanConfigPath(): string
    {
        return Config::get('podman.config_path');
    }

    protected function getPodmanTemporaryPath(): string
    {
        return Config::get('podman.temporary_path');
    }

    protected function shouldReloadSystemd(): bool
    {
        return Config::boolean('podman.reload_systemd');
    }

    protected function shouldUseSelinuxVolumeMapping(): bool
    {
        return Config::boolean('podman.selinux_volume_mapping');
    }
}
