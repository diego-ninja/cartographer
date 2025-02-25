<?php

namespace Ninja\Cartographer\Processors;

use Ninja\Cartographer\Attributes\Group;
use Ninja\Cartographer\Attributes\Request;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

final readonly class AttributeProcessor
{
    /**
     * @throws ReflectionException
     */
    public function getGroupAttribute(string $className): ?Group
    {
        $reflector = new ReflectionClass($className);
        $attributes = $reflector->getAttributes(Group::class);

        if (empty($attributes)) {
            return null;
        }

        /** @var Group $group */
        return $attributes[0]->newInstance();
    }

    /**
     * Get the Request attribute from a reflection method.
     * Note that closures (anonymous functions) cannot have attributes in PHP,
     * so this will always return null for ReflectionFunction.
     */
    public function getRequestAttribute(ReflectionMethod|ReflectionFunction $reflector): ?Request
    {
        if ($reflector instanceof ReflectionFunction) {
            return null;
        }

        $attributes = $reflector->getAttributes(Request::class);

        if (empty($attributes)) {
            return null;
        }

        /** @var Request $request */
        return $attributes[0]->newInstance();
    }
}
