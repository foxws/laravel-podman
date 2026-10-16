<?php

declare(strict_types=1);

use Foxws\Podman\Enums\QuadletUnitType;
use Foxws\Podman\Exceptions\QuadletRenderException;
use Foxws\Podman\Support\PodmanQuadletUnit;

it('splits a .quadlets file into its units', function () {
    $units = PodmanQuadletUnit::fromString(<<<'QUADLETS'
        # FileName=acme
        [Build]
        ImageTag=localhost/acme:latest
        ---
        # FileName=acme
        [Unit]
        Description=Acme

        [Container]
        Image=acme.build
        Secret=acme-env,target=/app/.env,mode=0400
        Secret=acme-key,type=env,target=APP_KEY
        ---
        # FileName=acme-data
        [Volume]
        VolumeName=systemd-acme-data
        QUADLETS);

    expect($units)->toHaveCount(3)
        ->and($units[0]->contents)->toBe("# FileName=acme\n[Build]\nImageTag=localhost/acme:latest\n")
        ->and(array_map(fn (PodmanQuadletUnit $unit) => $unit->fileName(), $units))->toBe(['acme.build', 'acme.container', 'acme-data.volume'])
        ->and(array_map(fn (PodmanQuadletUnit $unit) => $unit->serviceName(), $units))->toBe(['acme-build.service', 'acme.service', 'acme-data-volume.service'])
        ->and(array_map(fn (PodmanQuadletUnit $unit) => $unit->type, $units))->toBe([QuadletUnitType::Build, QuadletUnitType::Container, QuadletUnitType::Volume])
        ->and($units[1]->secrets())->toBe(['acme-env', 'acme-key'])
        ->and($units[0]->secrets())->toBe([]);
});

it('refuses units without a file name or Quadlet section', function (string $contents, string $reason) {
    expect(fn () => PodmanQuadletUnit::fromString($contents, 'app.quadlets'))
        ->toThrow(QuadletRenderException::class, "app.quadlets is not a valid .quadlets file: {$reason}.");
})->with([
    'no file name' => ["[Container]\nImage=acme\n", 'a unit has no "# FileName=" line'],
    'a path as file name' => ["# FileName=../acme\n[Container]\nImage=acme\n", 'a unit has no "# FileName=" line'],
    'no Quadlet section' => ["# FileName=acme\n[Unit]\nDescription=Acme\n", 'the unit acme has no Quadlet section, such as [Container]'],
]);

it('knows the service systemd generates for each unit type, and which ones run', function (QuadletUnitType $type, string $suffix, bool $runnable) {
    expect($type->serviceSuffix())->toBe($suffix)
        ->and($type->isRunnable())->toBe($runnable);
})->with([
    [QuadletUnitType::Container, '', true],
    [QuadletUnitType::Kube, '', true],
    [QuadletUnitType::Pod, '-pod', true],
    [QuadletUnitType::Volume, '-volume', false],
    [QuadletUnitType::Network, '-network', false],
    [QuadletUnitType::Build, '-build', false],
    [QuadletUnitType::Image, '-image', false],
    [QuadletUnitType::Artifact, '-artifact', false],
]);

it('reads the type from a section name', function () {
    expect(QuadletUnitType::fromSection('Container'))->toBe(QuadletUnitType::Container)
        ->and(QuadletUnitType::fromSection('Unit'))->toBeNull();
});
