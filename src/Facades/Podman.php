<?php

namespace Foxws\Podman\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Foxws\Podman\Podman
 */
class Podman extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Foxws\Podman\Podman::class;
    }
}
