<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Podman Service Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where Podman quadlet quadlet files are placed.
    | The default value is the vendor quadlet path, which can be overruled when needed.
    |
    */

    'quadlet_path' => env('PODMAN_QUADLET_PATH', '/app/quadlets'),

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

    'quadlet_uid' => env('PODMAN_QUADLET_UID', null),

    'quadlet_gid' => env('PODMAN_QUADLET_GID', null),

    /*
    |--------------------------------------------------------------------------
    | Podman Runtimes Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where Podman runtimes are placed. The default value
    | is 'runtimes', which can be overruled when needed.
    |
    */

    'runtimes_path' => env('PODMAN_RUNTIMES_PATH', '/app/runtimes'),

    /*
    |--------------------------------------------------------------------------
    | Proxy Path and Prefix
    |--------------------------------------------------------------------------
    |
    | These values determine the path and prefix used for Podman quadlet proxy files.
    | The default values are 'runtimes/proxy' and 'proxy', which can be
    | overruled when needed.
    |
    */

    'proxy_prefix' => env('PODMAN_PROXY_PREFIX', 'proxy'),

    'proxy_config_path' => env('PODMAN_PROXY_CONFIG_PATH', '/app/runtimes/proxy'),

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
    | Temporary Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where temporary Podman quadlet files are written
    | to before being installed. The default value is the system's temporary
    | directory, which can be overruled when needed.
    |
    */

    'temporary_path' => env('PODMAN_TEMPORARY_PATH', sys_get_temp_dir()),
];
