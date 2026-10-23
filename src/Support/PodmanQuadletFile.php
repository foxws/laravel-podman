<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Foxws\Podman\Exceptions\QuadletRenderException;
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
        return (new PodmanSubstitutions($this->path, $this->config))->all($preset);
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
        $result = preg_replace_callback(
            '/^Volume=(.*)$/m',
            function (array $matches): string {
                $segments = Str::of($matches[1])->explode(':');

                if ($segments->count() < 3) {
                    return "Volume={$matches[1]}";
                }

                $options = Str::of($segments->get(2, ''))
                    ->explode(',')
                    ->diff(['Z', 'z', 'U']);

                $segments = $options->isEmpty()
                    ? $segments->take(2)
                    : $segments->put(2, $options->implode(','));

                return 'Volume='.$segments->implode(':');
            },
            $contents,
        );

        return $result ?? throw QuadletRenderException::volumeFlagsNotRemoved(preg_last_error_msg());
    }
}
