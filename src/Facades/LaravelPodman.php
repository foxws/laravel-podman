<?php

namespace Foxws\LaravelPodman\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Foxws\LaravelPodman\LaravelPodman
 */
class LaravelPodman extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Foxws\LaravelPodman\LaravelPodman::class;
    }
}
