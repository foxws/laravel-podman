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
    | Reload Systemd
    |--------------------------------------------------------------------------
    |
    | This value determines whether to reload systemd after installing a service.
    | The default value is true, which means that systemd will be reloaded after installation.
    |
    */

    'reload_systemd' => env('PODMAN_RELOAD_SYSTEMD', true),
];
