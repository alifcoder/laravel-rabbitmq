<?php

namespace Alif\LaravelRabbitmq;

use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;

class BaseHandler
{
    public function handle(string $method, array $params): mixed
    {
        if (!method_exists($this, $method)) {
            throw new InvalidArgumentException("Unknown method: {$method}");
        }

        $type = new ReflectionMethod($this, $method)->getParameters()[0]?->getType();

        if (!$type instanceof ReflectionNamedType || !is_subclass_of($type->getName(), BaseDto::class)) {
            throw new InvalidArgumentException("Method {$method} must type-hint a BaseDto parameter");
        }

        return $this->$method($type->getName()::fromArray($params));
    }
}