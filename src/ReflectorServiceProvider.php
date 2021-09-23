<?php

namespace Cybex\Reflector;

use Illuminate\Support\ServiceProvider;

class ReflectorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerReflector();
    }

    /**
     * Register Reflector as a singleton.
     *
     * @return void
     */
    protected function registerReflector()
    {
        $this->app->singleton('Reflector', function ()
        {
            return new Reflector();
        });
    }
}