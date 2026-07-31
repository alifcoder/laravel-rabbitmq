<?php

namespace Alif\LaravelRabbitmq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class Client
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel          $channel    = null;

    private string $queue;
    private int    $rpcTimeout;

    private array   $params = [];
    private string  $method;
    private ?string $requestQueue = null;
    private mixed   $response;

    public function __construct()
    {
        $this->queue      = config('rabbitmq.default_queue', 'msgs');
        $this->rpcTimeout = (int) config('rabbitmq.rpc_timeout', 10);
    }

    public function __destruct()
    {
        $this->channel?->is_open() && $this->channel->close();
        $this->connection?->isConnected() && $this->connection->close();
    }

    /**
     * @throws \Exception
     */
    private function getConnection(): AMQPStreamConnection
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                    config('rabbitmq.host', 'localhost'),
                    (int) config('rabbitmq.port', 5672),
                    config('rabbitmq.user', 'guest'),
                    config('rabbitmq.password', 'guest')
            );
        }

        return $this->connection;
    }

    /**
     * @throws \Exception
     */
    private function getChannel(): AMQPChannel
    {
        if (!$this->channel || !$this->channel->is_open()) {
            $this->channel = $this->getConnection()->channel();
        }

        return $this->channel;
    }

    public function publish(?string $method = null, array $params = [], ?string $queue = null): void
    {
        $this->doPublish($method, $params, false, $queue);
    }

    public function request(?string $method = null, array $params = [], ?string $queue = null): static
    {
        $this->method       = $method;
        $this->params       = $params;
        $this->requestQueue = $queue;
        return $this;
    }

    public function getResult(): mixed
    {
        $this->response = null;
        $this->doPublish($this->method, $this->params, true, $this->requestQueue);
        return $this->response;
    }

    /**
     * @throws \Exception
     */
    private function doPublish(?string $method, array $params, bool $isRpc, ?string $queue = null): void
    {
        $queue   = $queue ?? $this->queue;
        $channel = $this->getChannel();
        $channel->queue_declare($queue, false, true, false, false);

        $properties   = [];
        $consumerTag  = null;

        if ($isRpc) {
            [$callbackQueue, ,] = $channel->queue_declare('', false, true, true, true);
            $correlationId = Uuid::uuid4()->toString();

            $properties = [
                    'correlation_id' => $correlationId,
                    'reply_to'       => $callbackQueue,
            ];

            $consumerTag = $channel->basic_consume($callbackQueue, '', false, true, false, false, function ($msg) use ($correlationId) {
                if ($msg->get('correlation_id') === $correlationId) {
                    $this->response = json_decode($msg->body, true);
                }
            });
        }

        $message = new AMQPMessage(json_encode([
                                                       'method' => $method,
                                                       'params' => $params,
                                               ]), $properties);

        $channel->basic_publish($message, '', $queue);

        if ($isRpc) {
            try {
                while (!$this->response) {
                    $channel->wait(null, false, $this->rpcTimeout);
                }
            } catch (AMQPTimeoutException) {
                throw new RuntimeException("RPC request '{$method}' timed out after {$this->rpcTimeout}s");
            } finally {
                $channel->basic_cancel($consumerTag);
            }
        } else {
            echo "Message published to queue {$queue}\n";
        }
    }

    /**
     * Consume $queue, invoking $callback(AMQPMessage $message) for each delivery.
     * The callback is responsible for acknowledging the message. Call wait()
     * afterwards to start the blocking consume loop.
     *
     * @throws \Exception
     */
    public function consume(string $queue, callable $callback): static
    {
        $channel = $this->getChannel();
        $channel->queue_declare($queue, false, true, false, false);
        $channel->basic_qos(0, 1, false);
        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        return $this;
    }

    public function wait(): void
    {
        $channel = $this->getChannel();

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }
}
