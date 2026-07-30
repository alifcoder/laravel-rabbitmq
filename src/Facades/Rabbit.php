<?php

namespace Alif\LaravelRabbitmq\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void publish(?string $method = null, array $params = [])
 * @method static \Alif\LaravelRabbitmq\Rabbit request(?string $method = null, array $params = [])
 * @method static mixed getResult()
 * @method static void consume()
 *
 * @see \Alif\LaravelRabbitmq\Rabbit
 */
class Rabbit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Alif\LaravelRabbitmq\Rabbit::class;
    }
}
