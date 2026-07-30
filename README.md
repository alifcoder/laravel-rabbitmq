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
| `queues` | — | `[default_queue]` — queues consumed by `rabbitmq:consume` |
| `dead_letter_queue` | `RABBITMQ_DEAD_LETTER_QUEUE` | `dead_letter_queue` |
| `handler` | — | `null` — your handler class, see below |

## Defining a handler

Every public method on your handler class is a dispatchable event: the message's `method` field
is matched against the method name, and if the method's first parameter type-hints a `BaseDto`
subclass, it's auto-hydrated from the message's `params`. The handler itself is resolved out of
the container, so its constructor gets normal Laravel dependency injection.

```php
// app/Rabbitmq/RabbitHandler.php
namespace App\Rabbitmq;

class RabbitHandler
{
    public function __construct(private readonly UserService $userService) {}

    public function createUser(UserCreateDto $dto): void
    {
        $this->userService->create($dto);
    }

    public function getUser(UserGetDto $dto): array
    {
        return $this->userService->getUser($dto);
    }
}
```

```php
// app/Rabbitmq/Dto/UserCreateDto.php
namespace App\Rabbitmq\Dto;

use Alif\LaravelRabbitmq\BaseDto;

class UserCreateDto extends BaseDto
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}
```

Point the config at it:

```php
// config/rabbitmq.php
'handler' => \App\Rabbitmq\RabbitHandler::class,
```

A full working example (handler, DTOs, and the service they delegate to) is in [`examples/`](examples).

## Usage

### Publish (fire-and-forget)

```php
use Alif\LaravelRabbitmq\Facades\Rabbit;

Rabbit::publish(method: 'createUser', params: ['email' => 'a@b.com', 'name' => 'Ada']);
```

### RPC request (wait for a reply)

```php
$result = Rabbit::request(method: 'getUser', params: ['id' => 5])->getResult();
```

Throws `RuntimeException` if no reply arrives within `rpc_timeout` seconds.

### Consuming

```bash
php artisan rabbitmq:consume
```

Consumes every queue listed in `queues`, dispatching each message to the configured `handler`.
Unhandled exceptions in the handler are caught: for RPC messages the error is returned to the
caller, for plain messages it's routed to `dead_letter_queue`.
