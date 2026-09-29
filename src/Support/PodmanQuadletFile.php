<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PodmanQuadletFile
{
    public function __construct(
        protected PodmanQuadletPath $path,
        protected PodmanConfig $config,
    ) {}

    /**
     * @return array<string, string>
     */
    public function substitutions(string $preset): array
    {
        return [
            '{{appEnv}}' => Config::string('app.env'),
            '{{appName}}' => Config::string('app.name'),
            '{{appUrl}}' => Config::string('app.url'),
            '{{appHost}}' => $this->config->domain(),
            '{{appUid}}' => (string) $this->config->uid(),
            '{{appGid}}' => (string) $this->config->gid(),
            '{{application}}' => $this->config->prefix(),
            '{{proxy}}' => $this->config->proxy(),
            '{{workingPath}}' => $this->path->workingPath(),
            '{{configPath}}' => $this->path->configPath(),
            '{{runtimePath}}' => $this->path->workingPresetRuntimePath($preset),
            '{{appUpstream}}' => $this->config->appUpstream(),
            '{{ondemand}}' => $this->config->onDemand(),
            '{{ondemandListen}}' => $this->config->onDemandListen(),
            '{{ondemandPort}}' => (string) $this->config->onDemandPort(),
            '{{ondemandIdleTimeout}}' => $this->config->onDemandIdleTimeout(),
            '{{ondemandServices}}' => $this->config->onDemandServices(),
            ...$this->config->customSubstitutions(),
        ];
    }

    public function renderSource(string $source, string $preset): string
    {
        $contents = strtr(File::get($source), $this->substitutions($preset));

        if (! $this->config->shouldUseSelinuxVolumeMapping()) {
            $contents = $this->removeSelinuxVolumeFlags($contents);
        }

        return $contents;
    }

    public function prepareSource(string $source, string $target, string $preset): string
    {
        File::ensureDirectoryExists(dirname($target));

        File::put($target, $this->renderSource($source, $preset));

        return $target;
    }

    public function publishDirectory(string $source, string $target, string $preset): void
    {
        File::ensureDirectoryExists($target);

        foreach (File::allFiles($source) as $file) {
            $destination = "{$target}/{$file->getRelativePathname()}";

            $this->prepareSource($file->getRealPath(), $destination, $preset);
        }
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
