<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Service Prefix
    |--------------------------------------------------------------------------
    |
    | This value is the prefix used for Podman quadlet files. The default value
    | is 'laravel', which can be overruled when needed.
    |
    */

    'quadlet_prefix' => env('PODMAN_QUADLET_PREFIX', env('APP_NAME', 'laravel')),

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
    | The default value is 'runtimes/config', which can be overruled when needed.
    |
    */

    'config_path' => env('PODMAN_CONFIG_PATH', 'runtimes/config'),

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
    | This value describes the services available to install. The 'default'
    | flag marks the services installed by the "podman:setup" command when
    | no explicit service is given. The 'requires' list names sibling
    | services that must be running first; these are wired into the
    | generated Quadlet files as Requires=/After= directives.
    |
    */

    'services' => [
        'pgsql' => [
            'default' => true,
            'requires' => [],
        ],

        'valkey' => [
            'default' => true,
            'requires' => [],
        ],

        'app' => [
            'default' => true,
            'requires' => ['pgsql', 'valkey'],
        ],

        'horizon' => [
            'default' => true,
            'requires' => ['valkey'],
        ],

        'reverb' => [
            'default' => true,
            'requires' => [],
        ],

        'schedule' => [
            'default' => true,
            'requires' => [],
        ],

        'inertia-ssr' => [
            'default' => false,
            'requires' => [],
        ],

        'mailpit' => [
            'default' => false,
            'requires' => [],
        ],

        'rustfs' => [
            'default' => false,
            'requires' => [],
        ],

        'typesense' => [
            'default' => false,
            'requires' => [],
        ],
    ],
];
