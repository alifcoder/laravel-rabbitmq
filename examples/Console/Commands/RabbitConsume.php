<?php

namespace Alif\LaravelRabbitmq\Examples\Console\Commands;

use Alif\LaravelRabbitmq\Client;
use Alif\LaravelRabbitmq\Examples\RabbitHandler;
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
            $data = json_decode($message->getBody(), true);

            try {
                $result = $handler->handle($data['method'] ?? '', $data['params'] ?? []);
                $message->ack();
            } catch (Throwable $exception) {
                // Reply with the error immediately, but also dead-letter the
                // original message (msgs.dlq) so failures stay inspectable.
                $result = ['error' => $exception->getMessage()];
                $message->nack(false);
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
