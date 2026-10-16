<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Foxws\Podman\Enums\QuadletUnitType;
use Foxws\Podman\Exceptions\QuadletRenderException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One unit of a rendered ".quadlets" file. A ".quadlets" file holds one or
 * more units, separated by "---" lines, each named by its "# FileName=" line
 * and typed by its Quadlet section, such as "[Container]" or "[Build]".
 */
readonly class PodmanQuadletUnit
{
    public function __construct(
        public string $name,
        public QuadletUnitType $type,
        public string $contents,
    ) {}

    /**
     * @return list<self>
     */
    public static function fromFile(string $path): array
    {
        return static::fromString((string) file_get_contents($path), $path);
    }

    /**
     * @return list<self>
     */
    public static function fromString(string $contents, string $source = 'the .quadlets file'): array
    {
        return array_values(Collection::make(preg_split('/^---$/m', $contents) ?: [])
            ->map(fn (string $chunk): string => Str::trim($chunk, "\n"))
            ->reject(fn (string $chunk): bool => $chunk === '')
            ->map(fn (string $chunk): self => static::fromChunk($chunk, $source))
            ->all());
    }

    protected static function fromChunk(string $chunk, string $source): self
    {
        if (! preg_match('/^# FileName=([A-Za-z0-9_.@-]+)$/m', $chunk, $name)) {
            throw QuadletRenderException::invalidUnit($source, 'a unit has no "# FileName=" line');
        }

        preg_match_all('/^\[([A-Za-z]+)\]$/m', $chunk, $sections);

        $type = Collection::make($sections[1])
            ->map(fn (string $section): ?QuadletUnitType => QuadletUnitType::fromSection($section))
            ->filter()
            ->first();

        if ($type === null) {
            throw QuadletRenderException::invalidUnit($source, "the unit {$name[1]} has no Quadlet section, such as [Container]");
        }

        return new self($name[1], $type, "{$chunk}\n");
    }

    /**
     * The file name Quadlet expects, such as "acme.container".
     */
    public function fileName(): string
    {
        return "{$this->name}.{$this->type->value}";
    }

    /**
     * The service systemd generates for the unit, such as
     * "acme-build.service".
     */
    public function serviceName(): string
    {
        return "{$this->name}{$this->type->serviceSuffix()}.service";
    }

    /**
     * The Podman secrets the unit uses, which must exist before it starts.
     *
     * @return list<PodmanQuadletSecret>
     */
    public function secrets(): array
    {
        preg_match_all('/^Secret=(.+)$/m', $this->contents, $matches);

        return array_map(fn (string $value): PodmanQuadletSecret => PodmanQuadletSecret::fromDirective($value), $matches[1]);
    }
}
