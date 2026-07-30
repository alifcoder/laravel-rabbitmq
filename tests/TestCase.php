<?php

namespace Alif\LaravelRabbitmq\Tests;

use Alif\LaravelRabbitmq\Facades\Rabbit;
use Alif\LaravelRabbitmq\RabbitmqServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RabbitmqServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Rabbit' => Rabbit::class,
        ];
    }
}
