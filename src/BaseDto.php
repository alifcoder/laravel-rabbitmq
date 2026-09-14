<?php

namespace Alif\LaravelRabbitmq;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

class BaseDto
{
    /**
     * Laravel validation rules keyed by field name, checked against the raw
     * $data array in fromArray() before it's hydrated into the DTO. Override
     * in subclasses; an empty array (the default) skips validation.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException if a required field is missing from $data
     * @throws ValidationException if $data fails static::rules()
     */
    public static function fromArray(array $data): static
    {
        $rules = static::rules();

        if ($rules !== []) {
            Validator::make($data, $rules)->validate();
        }

        $reflection = new ReflectionClass(static::class);
        $params = [];

        foreach ($reflection->getConstructor()->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $data)) {
                $params[] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $params[] = null;
            } else {
                throw new InvalidArgumentException(
                        sprintf('Missing required field "%s" for %s', $name, static::class)
                );
            }
        }

        return new static(...$params);
    }
}
