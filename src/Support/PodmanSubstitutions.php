<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

/**
 * Builds the "{{placeholder}}" => value map rendered into every template:
 * the built-in placeholders, plus the ones set in "podman.substitutions".
 */
class PodmanSubstitutions
{
    public function __construct(
        protected PodmanQuadletPath $path = new PodmanQuadletPath,
        protected PodmanConfig $config = new PodmanConfig,
    ) {}

    /**
     * @return array<string, string>
     */
    public function all(string $preset): array
    {
        $builtIn = $this->builtIn($preset);

        $custom = Arr::map(
            $this->config->customSubstitutions(),
            fn (string $value): string => strtr($value, $builtIn),
        );

        return [...$builtIn, ...$custom];
    }

    public function apply(string $contents, string $preset): string
    {
        return strtr($contents, $this->all($preset));
    }

    /**
     * @return array<string, string>
     */
    public function builtIn(string $preset): array
    {
        return [
            '{{appEnv}}' => Config::string('app.env'),
            '{{appName}}' => Config::string('app.name'),
            '{{appUrl}}' => Config::string('app.url'),
            '{{appHost}}' => $this->config->domain(),
            '{{appUid}}' => (string) $this->config->uid(),
            '{{appGid}}' => (string) $this->config->gid(),
            '{{systemctl}}' => $this->config->systemctl(),
            '{{application}}' => $this->config->prefix(),
            '{{proxy}}' => 'proxy',
            '{{workingPath}}' => $this->path->workingPath(),
            '{{configPath}}' => $this->path->configPath(),
            '{{runtimePath}}' => $this->path->workingPresetRuntimePath($preset),
            '{{appUpstream}}' => $this->config->appUpstream(),
            '{{ondemand}}' => $this->config->onDemand(),
            '{{ondemandListen}}' => $this->config->onDemandListen(),
            '{{ondemandPort}}' => (string) $this->config->onDemandPort(),
            '{{ondemandIdleTimeout}}' => $this->config->onDemandIdleTimeout(),
            '{{ondemandIdleWorkers}}' => implode(' ', $this->config->onDemandIdleWorkers()),
        ];
    }
}
