<?php

namespace App\Rabbitmq;

use Alif\LaravelRabbitmq\BaseDto;
use App\Rabbitmq\Dto\UserCreateDto;
use App\Rabbitmq\Dto\UserGetDto;
use App\Services\UserService;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Every public method here is a dispatchable event: handle() matches a
 * message's "method" field against a method name here, and auto-hydrates
 * the type-hinted BaseDto parameter from its "params".
 */
class RabbitHandler
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function createUser(UserCreateDto $dto): void
    {
        $this->userService->create($dto);
    }

    public function getUser(UserGetDto $dto): array
    {
        return $this->userService->getUser($dto);
    }

    public function handle(string $method, array $params): mixed
    {
        if (!method_exists($this, $method)) {
            throw new InvalidArgumentException("Unknown method: {$method}");
        }

        $type = (new ReflectionMethod($this, $method))->getParameters()[0]?->getType();

        if (!$type instanceof ReflectionNamedType || !is_subclass_of($type->getName(), BaseDto::class)) {
            throw new InvalidArgumentException("Method {$method} must type-hint a BaseDto parameter");
        }

        return $this->$method($type->getName()::fromArray($params));
    }
}
