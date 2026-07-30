<?php

namespace Alif\LaravelRabbitmq;

use Illuminate\Contracts\Container\Container;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

class Rabbit
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel          $channel    = null;
    private ?object               $handler    = null;

    private string $queue;
    private array  $queues;
    private string $deadLetterQueue;
    private int    $rpcTimeout;

    private array  $params = [];
    private string $method;
    private mixed  $response;

    public function __construct(private readonly Container $container)
    {
        $this->queue           = config('rabbitmq.default_queue', 'msgs');
        $this->queues          = config('rabbitmq.queues', [$this->queue]);
        $this->deadLetterQueue = config('rabbitmq.dead_letter_queue', 'dead_letter_queue');
        $this->rpcTimeout      = (int) config('rabbitmq.rpc_timeout', 10);
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

    private function getHandler(): object
    {
        if (!$this->handler) {
            $handlerClass = config('rabbitmq.handler');

            if (!$handlerClass) {
                throw new RuntimeException(
                        "No RabbitMQ handler configured. Set 'handler' in config/rabbitmq.php to your handler class."
                );
            }

            $this->handler = $this->container->make($handlerClass);
        }

        return $this->handler;
    }

    public function publish(?string $method = null, array $params = []): void
    {
        $this->doPublish($method, $params, false);
    }

    public function request(?string $method = null, array $params = []): static
    {
        $this->method = $method;
        $this->params = $params;
        return $this;
    }

    public function getResult(): mixed
    {
        $this->response = null;
        $this->doPublish($this->method, $this->params, true);
        return $this->response;
    }

    /**
     * @throws \Exception
     */
    private function doPublish(?string $method, array $params, bool $isRpc): void
    {
        $channel = $this->getChannel();
        $channel->queue_declare($this->queue, false, true, false, false);

        $properties = [];

        if ($isRpc) {
            [$callbackQueue, ,] = $channel->queue_declare('', false, true, true, true);
            $correlationId = Uuid::uuid4()->toString();

            $properties = [
                    'correlation_id' => $correlationId,
                    'reply_to'       => $callbackQueue,
            ];

            $channel->basic_consume($callbackQueue, '', false, true, false, false, function ($msg) use ($correlationId) {
                if ($msg->get('correlation_id') === $correlationId) {
                    $this->response = json_decode($msg->body, true);
                }
            });
        }

        $message = new AMQPMessage(json_encode([
                                                       'method' => $method,
                                                       'params' => $params,
                                               ]), $properties);

        $channel->basic_publish($message, '', $this->queue);

        if ($isRpc) {
            try {
                while (!$this->response) {
                    $channel->wait(null, false, $this->rpcTimeout);
                }
            } catch (AMQPTimeoutException) {
                throw new RuntimeException("RPC request '{$method}' timed out after {$this->rpcTimeout}s");
            }
        } else {
            echo "Message published to queue {$this->queue}\n";
        }
    }

    /**
     * @throws ReflectionException
     */
    private function invokeHandler(string $method, array $params): mixed
    {
        $handler    = $this->getHandler();
        $reflection = new ReflectionMethod($handler, $method);
        $type       = $reflection->getParameters()[0]?->getType();

        $argument = $params;
        if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), BaseDto::class)) {
            $argument = $type->getName()::fromArray($params);
        }

        return $handler->$method($argument);
    }

    public function consume(): void
    {
        $channel = $this->getChannel();
        $handler = $this->getHandler();

        foreach ($this->queues as $queue) {
            $channel->queue_declare($queue, false, true, false, false);

            $callback = function ($msg) use ($channel, $handler) {
                $data   = json_decode($msg->body, true);
                $method = $data['method'] ?? null;
                $params = $data['params'] ?? [];

                if (!$method || !method_exists($handler, $method)) {
                    $msg->ack();
                    return;
                }

                $isRpc = $msg->has('reply_to') && $msg->has('correlation_id');

                try {
                    $result = $this->invokeHandler($method, $params);
                } catch (Throwable $exception) {
                    $errorPayload = [
                            'method' => $method,
                            'params' => $params,
                            'error'  => [
                                    'file'    => $exception->getFile(),
                                    'line'    => $exception->getLine(),
                                    'message' => $exception->getMessage(),
                                    'time'    => date('Y-m-d H:i:s'),
                            ],
                    ];

                    if ($isRpc) {
                        $result = $errorPayload;
                    } else {
                        $channel->queue_declare($this->deadLetterQueue, false, true, false, false);
                        $channel->basic_publish(
                                new AMQPMessage(json_encode($errorPayload)),
                                '',
                                $this->deadLetterQueue
                        );
                        $msg->ack();
                        return;
                    }
                }

                if ($isRpc) {
                    $reply = new AMQPMessage(
                            json_encode($result),
                            ['correlation_id' => $msg->get('correlation_id')]
                    );
                    $channel->basic_publish($reply, '', $msg->get('reply_to'));
                }

                $msg->ack();
            };

            $channel->basic_qos(0, 1, false);
            $channel->basic_consume($queue, '', false, false, false, false, $callback);
        }

        echo "Waiting for messages. To exit press CTRL+C\n";
        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }
}
