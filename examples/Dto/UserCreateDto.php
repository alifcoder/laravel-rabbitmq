<?php

namespace Alif\LaravelRabbitmq\Examples\Dto;

use Alif\LaravelRabbitmq\BaseDto;

class UserCreateDto extends BaseDto
{
    public function __construct(
            public string $email,
            public string $name
    ) {
    }

    public static function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
