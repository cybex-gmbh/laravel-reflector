<?php

namespace Cybex\ModelReflector;

use Illuminate\Support\ServiceProvider;

class ModelReflectorServiceProvider extends ServiceProvider
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
        $this->mergeConfigFrom(__DIR__ . '/../config/modelReflector.php', 'modelReflector');

        $this->registerModelReflector();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['ModelReflector'];
    }

    /**
     * Register ModelReflector as a singleton.
     *
     * @return void
     */
    protected function registerModelReflector()
    {
        $this->app->singleton('ModelReflector', function ()
        {
            return new ModelReflector();
        });
    }

    protected function publishConfig()
    {
        $this->publishes([
            __DIR__ . '/../config/modelReflector.php' => config_path('modelReflector.php'),
        ], 'modelReflector.config');
    }
}
