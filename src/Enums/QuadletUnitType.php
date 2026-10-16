<?php

declare(strict_types=1);

namespace Foxws\Podman\Enums;

/**
 * A Quadlet unit type, named by its section, such as "[Container]", and the
 * file extension Quadlet expects, such as ".container".
 */
enum QuadletUnitType: string
{
    case Container = 'container';
    case Kube = 'kube';
    case Pod = 'pod';
    case Volume = 'volume';
    case Network = 'network';
    case Build = 'build';
    case Image = 'image';
    case Artifact = 'artifact';

    /**
     * The type for a unit section, such as "Container", or null when it's not
     * a Quadlet section, such as "Unit" or "Service".
     */
    public static function fromSection(string $section): ?self
    {
        return self::tryFrom(strtolower($section));
    }

    /**
     * What systemd appends to the unit's name for the service it generates,
     * such as "-build" for "acme-build.service".
     */
    public function serviceSuffix(): string
    {
        return match ($this) {
            self::Container, self::Kube => '',
            default => "-{$this->value}",
        };
    }

    /**
     * Whether units of this type run something themselves, rather than being
     * started as a dependency of one that does, like a volume or a build.
     */
    public function isRunnable(): bool
    {
        return match ($this) {
            self::Container, self::Kube, self::Pod => true,
            default => false,
        };
    }
}
