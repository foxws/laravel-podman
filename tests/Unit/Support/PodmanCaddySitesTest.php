<?php

declare(strict_types=1);

use Foxws\Podman\Support\PodmanCaddySites;

it('renders a reverse proxy site block per host', function () {
    $config = PodmanCaddySites::render([
        'ws.example.test' => 'systemd-app-reverb:6001',
        'mail.example.test' => 'systemd-app-mailpit:8025',
    ], 8000);

    expect($config)->toBe(<<<'CADDY'
    http://ws.example.test:8000 {
    	reverse_proxy systemd-app-reverb:6001
    }

    http://mail.example.test:8000 {
    	reverse_proxy systemd-app-mailpit:8025
    }
    CADDY);
});

it('skips sites with an empty host or upstream', function () {
    $config = PodmanCaddySites::render([
        '' => 'systemd-app-reverb:6001',
        'mail.example.test' => '',
        'ws.example.test' => 'systemd-app-reverb:6001',
    ], 8000);

    expect($config)->toBe("http://ws.example.test:8000 {\n\treverse_proxy systemd-app-reverb:6001\n}");
});

it('returns an empty string when no sites are given', function () {
    expect(PodmanCaddySites::render([], 8000))->toBe('');
});

it('extracts the host from a url', function () {
    expect(PodmanCaddySites::hostFromUrl('https://s3.example.test/bucket'))->toBe('s3.example.test');
});

it('returns an empty host for an empty url', function () {
    expect(PodmanCaddySites::hostFromUrl(''))->toBe('');
});

it('extracts host and port from a url', function () {
    expect(PodmanCaddySites::hostPortFromUrl('http://minio:9000'))->toBe('minio:9000');
});

it('returns an empty string when the url has no port', function () {
    expect(PodmanCaddySites::hostPortFromUrl('https://s3.amazonaws.com/bucket'))->toBe('');
});

it('returns an empty string when the url is empty', function () {
    expect(PodmanCaddySites::hostPortFromUrl(''))->toBe('');
});

it('builds a host:port pair', function () {
    expect(PodmanCaddySites::hostPort('reverb', 6001))->toBe('reverb:6001');
});

it('returns an empty string when the host is missing', function () {
    expect(PodmanCaddySites::hostPort(null, 6001))->toBe('');
    expect(PodmanCaddySites::hostPort('', 6001))->toBe('');
});

it('returns an empty string when the port is missing', function () {
    expect(PodmanCaddySites::hostPort('reverb', null))->toBe('');
    expect(PodmanCaddySites::hostPort('reverb', ''))->toBe('');
});
