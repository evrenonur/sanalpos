<?php

namespace EvrenOnur\SanalPos;

use Illuminate\Support\ServiceProvider;

class SanalPosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sanalpos.php', 'sanalpos');

        $this->app->singleton('sanalpos', function () {
            return new SanalPosClient;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sanalpos.php' => config_path('sanalpos.php'),
            ], 'sanalpos-config');
        }
    }
}
