<?php

namespace App\Rabbitmq\Dto;

use Alif\LaravelRabbitmq\BaseDto;

class UserGetDto extends BaseDto
{
    public function __construct(
        public string $id
    ) {
    }
}
