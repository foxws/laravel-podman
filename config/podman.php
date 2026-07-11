<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Podman Quadlet Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where the Podman quadlet files are stored.
    | The default value is rootless Podman quadlet path, which is ~/.config/containers/podman/.
    |
    */

    'quadlet_path' => env('PODMAN_QUADLET_PATH', '~/.config/containers/podman'),

    /*
    |--------------------------------------------------------------------------
    | Podman Quadlet Services
    |--------------------------------------------------------------------------
    |
    |
    */

    'quadlet_prefix' => env('PODMAN_QUADLET_PREFIX', 'laravel'),

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
