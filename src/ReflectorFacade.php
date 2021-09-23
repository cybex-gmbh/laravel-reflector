<?php

namespace Cybex\Reflector;

use Illuminate\Support\Facades\Facade;

class ReflectorFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'Reflector';
    }
}