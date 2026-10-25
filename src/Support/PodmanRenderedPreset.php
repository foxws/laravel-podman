<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Illuminate\Support\Collection;

/**
 * What PodmanPresetGenerator rendered for a preset, so callers can install
 * exactly that, without scanning the output folder.
 */
readonly class PodmanRenderedPreset
{
    /**
     * @param  list<string>  $quadlets  The rendered ".quadlets" files.
     * @param  list<string>  $systemdUnits  The rendered plain systemd units, such as sockets and timers.
     * @param  list<string>  $systemdDropIns  The rendered drop-ins of plain systemd units, such as "laravel-idle@my-app.service.d/idle.conf".
     */
    public function __construct(
        public string $preset,
        public string $path,
        public array $quadlets,
        public array $systemdUnits,
        public ?string $runtimesPath,
        public array $systemdDropIns = [],
    ) {}

    /**
     * Every unit of the rendered ".quadlets" files.
     *
     * @return list<PodmanQuadletUnit>
     */
    public function units(): array
    {
        return array_values(Collection::make($this->quadlets)
            ->flatMap(fn (string $file): array => PodmanQuadletUnit::fromFile($file))
            ->all());
    }

    /**
     * The rendered plain systemd units of the given type, such as "socket"
     * or "timer". Templates are left out, since they can't be enabled or
     * started without an instance.
     *
     * @return list<string>
     */
    public function systemdUnitsOfType(string $type): array
    {
        return array_values(array_filter(
            $this->systemdUnits,
            fn (string $file): bool => pathinfo($file, PATHINFO_EXTENSION) === $type && ! $this->isSystemdTemplate($file),
        ));
    }

    /**
     * The rendered plain systemd templates, such as "laravel-idle@.service",
     * which every app on the host shares.
     *
     * @return list<string>
     */
    public function systemdTemplates(): array
    {
        return array_values(array_filter($this->systemdUnits, $this->isSystemdTemplate(...)));
    }

    protected function isSystemdTemplate(string $file): bool
    {
        return str_contains(basename($file), '@.');
    }

    /**
     * The Podman secrets the preset's units use, once per name and target.
     * The same secret can be used under several targets, such as a database
     * password for both POSTGRES_PASSWORD and PGPASSWORD.
     *
     * @return list<PodmanQuadletSecret>
     */
    public function secrets(): array
    {
        return array_values(Collection::make($this->units())
            ->flatMap(fn (PodmanQuadletUnit $unit): array => $unit->secrets())
            ->unique(fn (PodmanQuadletSecret $secret): string => "{$secret->name}:{$secret->target}")
            ->all());
    }
}
