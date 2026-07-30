<?php

namespace Alif\LaravelRabbitmq\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void publish(?string $method = null, array $params = [], ?string $queue = null)
 * @method static \Alif\LaravelRabbitmq\Client request(?string $method = null, array $params = [], ?string $queue = null)
 * @method static mixed getResult()
 * @method static void consume()
 *
 * @see \Alif\LaravelRabbitmq\Client
 */
class Rabbit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Alif\LaravelRabbitmq\Client::class;
    }
}
