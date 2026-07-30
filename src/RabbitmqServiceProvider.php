<?php

namespace Alif\LaravelRabbitmq;

use Illuminate\Support\ServiceProvider;

class RabbitmqServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/rabbitmq.php', 'rabbitmq');

        $this->app->singleton(Client::class);
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
                __DIR__ . '/../config/rabbitmq.php' => config_path('rabbitmq.php'),
        ], 'rabbitmq-config');
    }
}
