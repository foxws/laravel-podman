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
];
