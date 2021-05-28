<?php

namespace OnPage;

use Illuminate\Support\ServiceProvider;

class OnPageServiceProvider extends ServiceProvider {
    public function register() {
    }

    public function boot() {
        $this->publishes([
            __DIR__.'/../config/onpage.php' => config_path('onpage.php'),
        ]);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->commands([
            Import::class,
            Rollback::class
        ]);

        Cache::refresh();

        // \DB::listen(function ($query) {
        //     log_backtrace();
        //     echo "$query->time $query->sql\n";
        // });



        // \DB::select('select 1');

    }
}
