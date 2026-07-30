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
     * Queue used by publish()/request() when no queue is passed explicitly.
     */
    'default_queue' => env('RABBITMQ_DEFAULT_QUEUE', 'msgs'),

];
