<?php

namespace App\Rabbitmq;

use App\Rabbitmq\Dto\UserCreateDto;
use App\Rabbitmq\Dto\UserGetDto;
use App\Services\UserService;

/**
 * Point config('rabbitmq.handler') at this class (or your own equivalent).
 * Every public method here is a dispatchable event: the method name is
 * matched against the incoming message's "method" field, and the
 * type-hinted BaseDto parameter is auto-hydrated from its "params".
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
}
