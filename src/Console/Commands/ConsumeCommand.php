<?php

namespace Alif\LaravelRabbitmq\Console\Commands;

use Illuminate\Console\Command;
use Alif\LaravelRabbitmq\Rabbit;

class ConsumeCommand extends Command
{
    protected $signature = 'rabbitmq:consume';

    protected $description = 'Consume messages from the configured RabbitMQ queues';

    public function handle(Rabbit $rabbit): int
    {
        $rabbit->consume();

        return self::SUCCESS;
    }
}
