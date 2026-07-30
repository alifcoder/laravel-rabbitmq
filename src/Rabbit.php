<?php

namespace Alif\LaravelRabbitmq;

class Rabbit
{
    private Client $client;

    public function __construct(
            private readonly string $method,
            private readonly array $params,
            private readonly ?string $queue = null
    ) {
        $this->client = app(Client::class);
    }

    /**
     * @throws \Exception
     */
    public function publish(): void
    {
        $this->client->publish(
                method: $this->method,
                params: $this->params,
                queue: $this->queue,
        );
    }

    /**
     * @throws \Exception
     */
    public function getResult(): mixed
    {
        return $this->client->request(
                method: $this->method,
                params: $this->params,
                queue: $this->queue,
        )->getResult();
    }
}
