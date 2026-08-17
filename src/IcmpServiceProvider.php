<?php

namespace Kamandlou\LaravelIcmp;

use Illuminate\Support\ServiceProvider;

class IcmpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/icmp.php', 'icmp');

        $this->app->singleton('icmp', fn ($app) => new IcmpManager($app['config']['icmp']));
        $this->app->alias('icmp', IcmpManager::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/icmp.php' => config_path('icmp.php')], 'icmp-config');
    }
}
