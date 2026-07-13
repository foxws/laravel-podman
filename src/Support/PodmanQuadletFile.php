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
    public function substitutions(): array
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
        ];
    }

    public function renderSource(string $source): string
    {
        $contents = strtr(File::get($source), $this->substitutions());

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
