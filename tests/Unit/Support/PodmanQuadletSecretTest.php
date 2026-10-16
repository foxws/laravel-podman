<?php

declare(strict_types=1);

use Foxws\Podman\Enums\QuadletSecretType;
use Foxws\Podman\Support\PodmanQuadletSecret;
use Foxws\Podman\Support\PodmanRenderedPreset;
use Illuminate\Support\Facades\File;

it('reads a secret from its directive', function (string $directive, string $name, QuadletSecretType $type, string $target) {
    $secret = PodmanQuadletSecret::fromDirective($directive);

    expect($secret->name)->toBe($name)
        ->and($secret->type)->toBe($type)
        ->and($secret->target)->toBe($target);
})->with([
    'env' => ['acme-pgsql-password,type=env,target=POSTGRES_PASSWORD', 'acme-pgsql-password', QuadletSecretType::Env, 'POSTGRES_PASSWORD'],
    'mount with options' => ['acme-env,target=/app/.env,mode=0400,uid=1000,gid=1000', 'acme-env', QuadletSecretType::Mount, '/app/.env'],
    'name only' => ['acme-cert', 'acme-cert', QuadletSecretType::Mount, 'acme-cert'],
]);

it('lists each secret of a preset once per target', function () {
    $path = sys_get_temp_dir().'/podman-secrets-'.uniqid();
    File::ensureDirectoryExists($path);
    File::put("{$path}/pgsql.quadlets", <<<'QUADLETS'
        # FileName=acme-pgsql
        [Container]
        Secret=acme-pgsql-password,type=env,target=POSTGRES_PASSWORD
        Secret=acme-pgsql-password,type=env,target=PGPASSWORD
        ---
        # FileName=acme
        [Container]
        Secret=acme-env,target=/app/.env
        ---
        # FileName=acme-horizon
        [Container]
        Secret=acme-env,target=/app/.env
        QUADLETS);

    $rendered = new PodmanRenderedPreset('production', $path, ["{$path}/pgsql.quadlets"], [], null);

    expect(array_map(fn ($secret) => "{$secret->name}:{$secret->target}", $rendered->secrets()))->toBe([
        'acme-pgsql-password:POSTGRES_PASSWORD',
        'acme-pgsql-password:PGPASSWORD',
        'acme-env:/app/.env',
    ]);

    File::deleteDirectory($path);
});
