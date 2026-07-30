<?php

use Alif\LaravelRabbitmq\Rabbit;

if (!function_exists('rabbit')) {
    function rabbit(string $method, array $params = [], ?string $queue = null): Rabbit
    {
        return new Rabbit($method, $params, $queue);
    }
}
