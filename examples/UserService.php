<?php

namespace App\Services;

use App\Rabbitmq\Dto\UserCreateDto;
use App\Rabbitmq\Dto\UserGetDto;

class UserService
{
    public function create(UserCreateDto $object): void
    {
        echo "User created with email: $object->email and name: $object->name\n";
    }

    public function getUser(UserGetDto $object): array
    {
        return [
            'id' => $object->id,
            'name' => 'John Doe',
            'email' => 'test@mail.com'
        ];
    }
}
