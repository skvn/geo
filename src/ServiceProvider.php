<?php namespace Skvn\Geo;

use Illuminate\Support\ServiceProvider as LServiceProvider;

class ServiceProvider extends LServiceProvider {



    public function boot()
    {
    }

    public function register()
    {
        $this->publishes([__DIR__ . '/../config/geo.php' => config_path('geo.php')], 'config');
        $this->registerCommands();
    }


    protected function registerCommands()
    {
        $this->app->bindIf('command.geo.code', function () {
            return new Console\GeoCodeCommand;
        });

        $this->commands(
            'command.geo.code'
        );
    }

    public function provides()
    {
        return [
            'command.geo.code',
        ];
    }
}