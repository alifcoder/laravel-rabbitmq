<?php

namespace App\Console\Commands;

use Alif\LaravelRabbitmq\Client;
use App\Rabbitmq\RabbitHandler;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitConsume extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbit:consume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume rabbit messages';

    /**
     * Execute the console command.
     */
    public function handle(Client $client, RabbitHandler $handler): void
    {
        $client->consume(config('rabbitmq.default_queue'), function (AMQPMessage $message) use ($handler) {
            $message->ack();

            $data = json_decode($message->getBody(), true);

            try {
                $result = $handler->handle($data['method'] ?? '', $data['params'] ?? []);
            } catch (Throwable $exception) {
                $result = ['error' => $exception->getMessage()];
            }

            if ($message->has('reply_to') && $message->has('correlation_id')) {
                $message->getChannel()->basic_publish(
                        new AMQPMessage(json_encode($result), ['correlation_id' => $message->get('correlation_id')]),
                        '',
                        $message->get('reply_to')
                );
            }
        })->wait();
    }
}
