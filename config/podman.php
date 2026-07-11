<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Podman Quadlet Mode
    |--------------------------------------------------------------------------
    |
    | This value determines the mode in which Podman operates. The default value
    | is 'rootless', which means Podman will run and install without root privileges.
    |
    */

    'quadlet_mode' => env('PODMAN_QUADLET_MODE', 'rootless'),

    /*
    |--------------------------------------------------------------------------
    | Podman Quadlet Service Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where Podman quadlet service files are placed.
    | The default value is the vendor service path, which can be overruled when needed.
    |
    */

    'quadlet_services_path' => env('PODMAN_QUADLET_SERVICE_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Podman Quadlet Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where Podman quadlet files are written to. The
    | default value is the user's home directory, which can be overruled when needed.
    |
    */

    'quadlet_root_path' => env('PODMAN_QUADLET_ROOT_PATH', '/etc/containers/systemd'),

    'quadlet_rootless_path' => env('PODMAN_QUADLET_ROOTLESS_PATH', '~/.config/containers/systemd'),

    /*
    |--------------------------------------------------------------------------
    | Quadlet Prefix
    |--------------------------------------------------------------------------
    |
    | This value is the prefix used for Podman quadlet files. The default value
    | is 'laravel', which can be overruled when needed.
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
