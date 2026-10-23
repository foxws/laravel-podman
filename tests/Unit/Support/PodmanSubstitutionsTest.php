<?php

declare(strict_types=1);

use Foxws\Podman\Support\PodmanSubstitutions;

beforeEach(function () {
    $this->substitutions = new PodmanSubstitutions;

    config(['app.url' => 'https://laravel.test', 'podman.quadlet_prefix' => 'acme']);
});

it('includes the built-in placeholders', function () {
    expect($this->substitutions->all('production'))
        ->toHaveKey('{{appHost}}', 'laravel.test')
        ->toHaveKey('{{application}}', 'acme');
});

it('wraps custom keys without braces', function () {
    config(['podman.substitutions' => ['viteUrl' => 'https://vite.laravel.test']]);

    expect($this->substitutions->apply('VITE_URL={{viteUrl}}', 'production'))
        ->toBe('VITE_URL=https://vite.laravel.test');
});

it('renders a null custom value as an empty string', function () {
    config(['podman.substitutions' => ['{{mailHost}}' => null]]);

    expect($this->substitutions->apply('MAIL={{mailHost}}', 'production'))->toBe('MAIL=');
});

it('resolves built-in placeholders inside custom values', function () {
    config(['podman.substitutions' => [
        'viteHost' => 'vite.{{appHost}}',
        'viteUpstream' => 'systemd-{{application}}-vite:5173',
    ]]);

    expect($this->substitutions->apply('{{viteHost}} -> {{viteUpstream}}', 'production'))
        ->toBe('vite.laravel.test -> systemd-acme-vite:5173');
});

it('lets a custom value override a built-in placeholder', function () {
    config(['podman.substitutions' => ['application' => 'overridden']]);

    expect($this->substitutions->apply('{{application}}', 'production'))->toBe('overridden');
});

it('names the proxy "proxy" unless a substitution renames it', function () {
    config(['podman.substitutions' => []]);

    expect($this->substitutions->apply('{{proxy}}', 'proxy'))->toBe('proxy');

    config(['podman.substitutions' => ['proxy' => 'edge']]);

    expect($this->substitutions->apply('{{proxy}}', 'proxy'))->toBe('edge');
});
