<?php

namespace HappyDemon\Lists;

use HappyDemon\Lists\Console\Commands\GenerateTable;
use Illuminate\Support\ServiceProvider as ParentProvider;

class ServiceProvider extends ParentProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        // Register our make:table command
        $this->app->singleton('command.make.table', function ($app)
        {
            return new GenerateTable();
        });

        $this->commands('command.make.table');
    }

    public function boot()
    {
        // Make views available
        $view_path = __DIR__ . '/../views';

        $this->loadViewsFrom($view_path, 'lists');

        // Make config available
        $config_path = __DIR__ . '/../config/lists.php';
        $this->mergeConfigFrom($config_path, 'lists');

        // publish files
        $this->publishes([
            $view_path   => base_path('resources/views/vendor/lists'),
            $config_path => config_path('lists.php')
        ]);

        // include routes
        include __DIR__ . '/Http/routes.php';
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [

            'command.make.table'
        ];
    }
}