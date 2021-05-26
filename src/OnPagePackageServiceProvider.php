<?php

namespace OnPage;

use Illuminate\Support\ServiceProvider;

class OnPagePackageServiceProvider extends ServiceProvider {
    public function register() {
        // require_once __DIR__.'/helpers.php';
    }

    public function boot() {
        $this->publishes([
            __DIR__.'/../config/onpage.php' => config_path('onpage.php'),
        ]);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Models\Resource::cacheResources();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Import::class,
                Rollback::class
            ]);
        }
    }
}
