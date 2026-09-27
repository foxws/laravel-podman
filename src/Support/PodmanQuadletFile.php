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
            '{{appHost}}' => $this->path->domain(),
            '{{appUid}}' => (string) $this->path->uid(),
            '{{appGid}}' => (string) $this->path->gid(),
            '{{application}}' => $this->path->prefix(),
            '{{proxy}}' => $this->path->proxy(),
            '{{workingPath}}' => $this->path->workingPath(),
            '{{configPath}}' => $this->path->configPath(),
            '{{runtimePath}}' => $this->path->workingPresetRuntimePath($preset),
            '{{appUpstream}}' => $this->path->appUpstream(),
            '{{ondemandListen}}' => $this->path->onDemandListen(),
            '{{ondemandPort}}' => (string) $this->path->onDemandPort(),
            '{{ondemandIdleTimeout}}' => $this->path->onDemandIdleTimeout(),
            ...$this->path->customSubstitutions(),
        ];
    }

    public function renderSource(string $source, string $preset): string
    {
        $contents = strtr(File::get($source), $this->substitutions($preset));

        if (! $this->path->shouldUseSelinuxVolumeMapping()) {
            $contents = $this->removeSelinuxVolumeFlags($contents);
        }

        if ($this->path->usesOnDemand($preset)) {
            $contents = $this->replaceBindsToWithPartOf($contents);
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

    /**
     * Sidecars declare "BindsTo=" the app so they stop with it. systemd
     * counts that as the app being needed, which keeps an on-demand app
     * ("StopWhenUnneeded=yes") from ever stopping. "PartOf=" still stops
     * them with the app without holding it up.
     */
    public function replaceBindsToWithPartOf(string $contents): string
    {
        return preg_replace('/^BindsTo=/m', 'PartOf=', $contents);
    }
}
