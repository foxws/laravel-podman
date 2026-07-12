<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Podman Quadlet Service Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where Podman quadlet service files are placed.
    | The default value is the vendor service path, which can be overruled when needed.
    |
    */

    'quadlet_services_path' => env('PODMAN_QUADLET_SERVICE_PATH', 'storage/app/quadlets'),

    /*
    |--------------------------------------------------------------------------
    | Podman Quadlet Container Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where Podman quadlet container files are placed.
    | The default value is the vendor container path, which can be overruled when needed.
    |
    */

    'quadlet_container_path' => env('PODMAN_QUADLET_CONTAINER_PATH', 'storage/app/containers'),

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

    /*
    |--------------------------------------------------------------------------
    | Quadlet Prefix
    |--------------------------------------------------------------------------
    |
    | This value is the prefix used for Podman quadlet files. The default value
    | is 'laravel', which can be overruled when needed.
    |
    */

    'quadlet_prefix' => env('PODMAN_QUADLET_PREFIX', env('APP_NAME', 'laravel')),

    /*
    |--------------------------------------------------------------------------
    | Quadlet Proxy Path and Prefix
    |--------------------------------------------------------------------------
    |
    | These values determine the path and prefix used for Podman quadlet proxy files.
    | The default values are 'storage/app/containers' and 'proxy', which can be
    | overruled when needed.
    |
    */

    'quadlet_proxy_path' => env('PODMAN_QUADLET_PROXY_PATH', 'storage/app/containers'),

    'quadlet_proxy_prefix' => env('PODMAN_QUADLET_PROXY_PREFIX', 'proxy'),

    /*
    |--------------------------------------------------------------------------
    | Quadlet UID and GID
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
    | This value determines whether to reload systemd after installing a service.
    | The default value is true, which means that systemd will be reloaded after installation.
    |
    */

    'reload_systemd' => env('PODMAN_RELOAD_SYSTEMD', true),
];
