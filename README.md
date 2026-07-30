# laravel-rabbitmq

Simple RabbitMQ client for Laravel with pub/sub and RPC-style request/response support.

## Installation

```bash
composer require alifcoder/laravel-rabbitmq
```

The service provider and `Rabbit` facade are auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=rabbitmq-config
```

## Configuration

`config/rabbitmq.php`:

| Key | Env var | Default |
|---|---|---|
| `host` | `RABBITMQ_HOST` | `localhost` |
| `port` | `RABBITMQ_PORT` | `5672` |
| `user` | `RABBITMQ_USER` | `guest` |
| `password` | `RABBITMQ_PASSWORD` | `guest` |
| `rpc_timeout` | `RABBITMQ_RPC_TIMEOUT` | `10` (seconds) |
| `default_queue` | `RABBITMQ_DEFAULT_QUEUE` | `msgs` |

## What the package provides vs. what you write

`Alif\LaravelRabbitmq\Client` is a thin, low-level primitive: connection/channel management,
`publish()`, `request()->getResult()`, and a `consume(queue, callback)->wait()` pair where you get
the raw `PhpAmqpLib\Message\AMQPMessage` and are responsible for acknowledging it yourself.

There's no handler registry, method dispatch, or dead-letter routing built into the package —
that's application logic, not library logic. [`examples/`](examples) shows one way to build it:
a `RabbitHandler` class (dispatched to by method name, with `BaseDto` auto-hydrating typed
parameters from the message `params`) plus a `RabbitConsume` command that wires it up, RPC replies
included. Copy it into your project and adjust it to taste — see "Consuming" below.

## Usage

### `rabbit()` helper (recommended)

```php
// Publish (fire-and-forget)
rabbit('createUser', ['email' => 'a@b.com', 'name' => 'Ada'])->publish();

// RPC request (wait for a reply)
$result = rabbit('getUser', ['id' => 5])->getResult();

// Optionally target a queue other than `default_queue`
rabbit('createUser', ['email' => 'a@b.com', 'name' => 'Ada'], queue: 'pos')->publish();
```

`rabbit(string $method, array $params = [], ?string $queue = null): Rabbit` returns a
`Alif\LaravelRabbitmq\Rabbit` instance — call `->publish()` or `->getResult()` on it.

### Facade

The same operations are available on the `Rabbit` facade, which proxies the underlying
`Alif\LaravelRabbitmq\Client` singleton directly:

```php
use Alif\LaravelRabbitmq\Facades\Rabbit;

Rabbit::publish(method: 'createUser', params: ['email' => 'a@b.com', 'name' => 'Ada']);

$result = Rabbit::request(method: 'getUser', params: ['id' => 5])->getResult();
```

Both throw `RuntimeException` if an RPC request gets no reply within `rpc_timeout` seconds.

### Consuming

```php
use Alif\LaravelRabbitmq\Client;
use PhpAmqpLib\Message\AMQPMessage;

$client->consume('erp', function (AMQPMessage $message) {
    $message->ack();

    $data = json_decode($message->getBody(), true);
    // ... do something with $data['method'] / $data['params'] ...

    // if the message was sent via request()->getResult(), reply to it:
    if ($message->has('reply_to') && $message->has('correlation_id')) {
        $message->getChannel()->basic_publish(
            new AMQPMessage(json_encode($result), ['correlation_id' => $message->get('correlation_id')]),
            '',
            $message->get('reply_to')
        );
    }
})->wait();
```

- You choose the queue per call — nothing is read from `config('rabbitmq.queues')`.
- You ack (or nack/reject) the message yourself; the package won't do it for you.
- `wait()` blocks and runs the consume loop; call it once after registering every `consume()` callback you need.

[`examples/Console/Commands/RabbitConsume.php`](examples/Console/Commands/RabbitConsume.php) wraps
this in a full `rabbit:consume` artisan command — method/DTO dispatch to a `RabbitHandler`, RPC
replies, and per-message error handling included. Copy it into your project:

```bash
cp vendor/alifcoder/laravel-rabbitmq/examples/Console/Commands/RabbitConsume.php app/Console/Commands/
cp vendor/alifcoder/laravel-rabbitmq/examples/RabbitHandler.php app/Rabbitmq/RabbitHandler.php
```

Laravel auto-discovers commands under `app/Console/Commands/`, so `php artisan rabbit:consume`
works immediately once it's there — no registration needed.
