<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PodmanQuadletFile
{
    public function __construct(
        protected PodmanQuadletPath $path,
    ) {}

    /**
     * @return array<string, string>
     */
    public function substitutions(?string $service = null): array
    {
        return [
            '{{app-env}}' => Config::string('app.env'),
            '{{app-name}}' => Config::string('app.name'),
            '{{app-url}}' => Config::string('app.url'),
            '{{app-host}}' => $this->path->domain(),
            '{{app-uid}}' => (string) $this->path->uid(),
            '{{app-gid}}' => (string) $this->path->gid(),
            '{{application}}' => $this->path->prefix(),
            '{{base-path}}' => base_path(),
            '{{config-path}}' => $this->path->configPath(),
            '{{runtime-path}}' => $this->path->runtimePath(),
            '{{requirements}}' => $this->requirements($service),
        ];
    }

    public function renderSource(string $source): string
    {
        $service = pathinfo($source, PATHINFO_FILENAME);

        $contents = strtr(File::get($source), $this->substitutions($service));

        if (! $this->path->shouldUseSelinuxVolumeMapping()) {
            $contents = $this->removeSelinuxVolumeFlags($contents);
        }

        return $contents;
    }

    public function prepareSource(string $source, string $target): string
    {
        File::ensureDirectoryExists(dirname($target));

        File::put($target, $this->renderSource($source));

        return $target;
    }

    public function publishDirectory(string $source, string $target): void
    {
        File::ensureDirectoryExists($target);

        foreach (File::allFiles($source) as $file) {
            $destination = "{$target}/{$file->getRelativePathname()}";

            $this->prepareSource($file->getRealPath(), $destination);
        }
    }

    /**
     * Build the Requires=/After= lines for the services a Quadlet depends on,
     * as configured under "podman.services.{service}.requires".
     */
    protected function requirements(?string $service): string
    {
        if ($service === null) {
            return '';
        }

        $units = Collection::make(Config::array("podman.services.{$service}.requires", []))
            ->map(fn (string $dependency): string => $this->quadletUnitName($dependency))
            ->implode(' ');

        if ($units === '') {
            return '';
        }

        return "Requires={$units}\nAfter={$units}";
    }

    protected function quadletUnitName(string $service): string
    {
        $prefix = $this->path->prefix();

        return $service === 'app' ? "{$prefix}.container" : "{$prefix}-{$service}.container";
    }

    public function removeSelinuxVolumeFlags(string $contents): string
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
}
