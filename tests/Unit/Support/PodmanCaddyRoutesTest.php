<?php

declare(strict_types=1);

use Foxws\Podman\Support\PodmanCaddyRoutes;

it('renders routes that strip or keep the path prefix', function () {
    $config = PodmanCaddyRoutes::make()
        ->route('/ws', 'systemd-app-reverb', 6001)
        ->route('mail/', 'systemd-app-mailpit', 8025, stripPrefix: false)
        ->render();

    expect($config)->toBe(<<<'CADDY'
    redir /ws /ws/

    handle_path /ws/* {
    	reverse_proxy systemd-app-reverb:6001
    }

    redir /mail /mail/

    handle /mail/* {
    	reverse_proxy systemd-app-mailpit:8025
    }
    CADDY);
});

it('skips routes with an empty path, host or port', function () {
    $config = PodmanCaddyRoutes::make()
        ->route(null, 'systemd-app-reverb', 6001)
        ->route('/', 'systemd-app', 8000)
        ->route('/mail', null, 8025)
        ->route('/ws', 'systemd-app-reverb', null)
        ->upstream('/s3', '')
        ->render();

    expect($config)->toBe('');
});

it('accepts a host:port upstream', function () {
    expect(PodmanCaddyRoutes::make()->upstream('/s3', 'rustfs:9000')->render())
        ->toContain("handle_path /s3/* {\n\treverse_proxy rustfs:9000\n}");
});
