<?php

return [

    'host'     => env('RABBITMQ_HOST', 'localhost'),
    'port'     => env('RABBITMQ_PORT', 5672),
    'user'     => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),

    /*
     * Seconds to wait for a reply before an RPC request() call throws.
     */
    'rpc_timeout' => env('RABBITMQ_RPC_TIMEOUT', 10),

    /*
     * Queue used by publish()/request() and the default queue consumed
     * when 'queues' below is empty.
     */
    'default_queue' => env('RABBITMQ_DEFAULT_QUEUE', 'msgs'),

    /*
     * Queues consumed by `php artisan rabbitmq:consume`.
     */
    'queues' => [
        env('RABBITMQ_DEFAULT_QUEUE', 'msgs'),
    ],

    'dead_letter_queue' => env('RABBITMQ_DEAD_LETTER_QUEUE', 'dead_letter_queue'),

    /*
     * Class resolved out of the container to handle incoming messages.
     * Every public method on it is a dispatchable event method name;
     * the type-hint of its first parameter (if it extends BaseDto) is
     * auto-hydrated from the message params. See examples/RabbitHandler.php.
     */
    'handler' => null,

];
