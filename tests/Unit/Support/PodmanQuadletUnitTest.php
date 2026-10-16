<?php

declare(strict_types=1);

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
        ->and(array_map(fn (PodmanQuadletUnit $unit) => $unit->isRunnable(), $units))->toBe([false, true, false])
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
