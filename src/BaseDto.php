<?php

namespace Alif\LaravelRabbitmq;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

class BaseDto
{
    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException if a required field is missing from $data
     */
    public static function fromArray(array $data): static
    {
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
