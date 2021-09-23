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
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/reflector.php', 'reflector');

        $this->registerReflector();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['Reflector'];
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

    protected function publishConfig()
    {
        $this->publishes([
            __DIR__ . '/../config/reflector.php' => config_path('reflector.php'),
        ], 'reflector.config');
    }
}
