<?php

namespace Alif\LaravelRabbitmq\Examples\Dto;

use Alif\LaravelRabbitmq\BaseDto;

class UserGetDto extends BaseDto
{
    public function __construct(
        public string $id
    ) {
    }
}
