<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Services Prefix
    |--------------------------------------------------------------------------
    |
    | This value is the prefix used for Podman quadlet files. The default value
    | is the application name, which can be overruled when needed.
    |
    */

    'quadlet_prefix' => env('PODMAN_QUADLET_PREFIX', env('APP_NAME', 'laravel')),

    'proxy_prefix' => env('PODMAN_PROXY_PREFIX', 'proxy'),

    /*
    |--------------------------------------------------------------------------
    | Quadlets Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where Podman quadlet files are located.
    | The default value is null, which means the vendor default path will be used.
    */

    'quadlets_path' => env('PODMAN_QUADLETS_PATH'),

    'runtimes_path' => env('PODMAN_RUNTIMES_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Service UID and GID
    |--------------------------------------------------------------------------
    |
    | These values determine the UID and GID used for Podman quadlet files.
    | The default values are null, which means the system's UID and GID
    | will be used. These can be overruled when needed.
    |
    */

    'quadlet_uid' => env('PODMAN_QUADLET_UID'),

    'quadlet_gid' => env('PODMAN_QUADLET_GID'),

    /*
    |--------------------------------------------------------------------------
    | Podman Runtimes Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where Podman runtimes are placed. The default value
    | is 'runtimes', which can be overruled when needed.
    |
    */

    'runtime_path' => env('PODMAN_RUNTIME_PATH', 'runtimes'),

    /*
    |--------------------------------------------------------------------------
    | Podman Config Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where Podman configuration files are placed.
    | The default value is 'runtimes', which can be overruled when needed.
    |
    */

    'config_path' => env('PODMAN_CONFIG_PATH', 'runtimes'),

    /*
    |--------------------------------------------------------------------------
    | SELinux Volume Mapping
    |--------------------------------------------------------------------------
    |
    | This value determines whether the Z, z and U SELinux volume flags are
    | kept on Volume= entries. Disable this on hosts that do not run SELinux,
    | since these flags are rejected there.
    |
    */

    'selinux_volume_mapping' => env('PODMAN_SELINUX_VOLUME_MAPPING', true),

    /*
    |--------------------------------------------------------------------------
    | Reload Systemd
    |--------------------------------------------------------------------------
    |
    | This value determines whether to reload systemd after installing a quadlet.
    | The default value is true, which means that systemd will be reloaded after installation.
    |
    */

    'reload_systemd' => env('PODMAN_RELOAD_SYSTEMD', true),

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    |
    | This value lists the services installed by the "podman:setup" command
    | when no explicit service is given. Any service not listed here can
    | still be installed manually with "podman:install". Accepts either a
    | comma-separated string (handy for the PODMAN_DEFAULT_SERVICES env
    | variable) or a plain array of service names.
    |
    */

    'services' => env('PODMAN_DEFAULT_SERVICES', [
        'proxy',
        'app',
        'pgsql',
        'valkey',
        'horizon',
        'reverb',
        'schedule',
    ]),
];
