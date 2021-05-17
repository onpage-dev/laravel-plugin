<?php

namespace OnPage;

use Illuminate\Support\ServiceProvider;

function hello_world() {
    return 'hello world';
}

class OnPagePackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/onpage.php' => config_path('onpage.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Import::class,
            ]);
        }

    }
}
