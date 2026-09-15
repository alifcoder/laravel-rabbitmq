<?php

return [
        'host'                     => env('AMQP_HOST', '127.0.0.1'),
        'port'                     => env('AMQP_PORT', 5672),
        'username'                 => env('AMQP_USER', 'guest'),
        'password'                 => env('AMQP_PASSWORD', 'guest'),

        /*
         * Seconds to wait for a reply before an RPC request() call throws.
         */
        'rpc_timeout'              => env('RABBITMQ_RPC_TIMEOUT', 10),

        /*
         * Queue used by publish()/request() when no queue is passed explicitly.
         */
        'default_queue'            => env('RABBITMQ_DEFAULT_QUEUE', 'msgs'),

        /*
         * Exchange that consume()-declared queues dead-letter into when a
         * message is nacked/rejected without requeue, or expires via TTL.
         */
        'dead_letter_exchange'     => env('RABBITMQ_DEAD_LETTER_EXCHANGE', 'dlx'),

        /*
         * Suffix appended to a queue's name to derive its dead-letter queue,
         * e.g. "msgs" -> "msgs.dlq".
         */
        'dead_letter_queue_suffix' => env('RABBITMQ_DEAD_LETTER_QUEUE_SUFFIX', '.dlq'),

];
