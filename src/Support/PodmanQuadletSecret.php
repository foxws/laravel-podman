<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Foxws\Podman\Enums\QuadletSecretType;
use Illuminate\Support\Str;

/**
 * A "Secret=" directive of a container unit: the Podman secret it uses, and
 * where the container gets it.
 */
readonly class PodmanQuadletSecret
{
    public function __construct(
        public string $name,
        public QuadletSecretType $type,
        public string $target,
    ) {}

    /**
     * From a directive's value, such as
     * "acme-pgsql-password,type=env,target=POSTGRES_PASSWORD". Without a
     * type, the secret is mounted as a file; without a target, it's named
     * after the secret.
     */
    public static function fromDirective(string $value): self
    {
        $parts = explode(',', Str::trim($value));
        $options = [];

        foreach (array_slice($parts, 1) as $part) {
            [$key, $option] = array_pad(explode('=', $part, 2), 2, '');
            $options[$key] = $option;
        }

        return new self(
            name: $parts[0],
            type: QuadletSecretType::tryFrom($options['type'] ?? '') ?? QuadletSecretType::Mount,
            target: ($options['target'] ?? '') !== '' ? $options['target'] : $parts[0],
        );
    }
}
