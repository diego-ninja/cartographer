<?php

namespace Ninja\Cartographer\Support;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

final readonly class RouteReflector
{
    /**
     * @throws ReflectionException
     */
    public static function action(Route $route): ReflectionMethod|ReflectionFunction|null
    {
        $action = $route->getAction();

        if (self::containsSerializedClosure($action)) {
            $action['uses'] = unserialize($action['uses'])->getClosure();
        }

        if ($action['uses'] instanceof Closure) {
            return new ReflectionFunction($action['uses']);
        }

        if ( ! is_string($action['uses'])) {
            return null;
        }

        $routeData = explode('@', $action['uses']);
        if (2 !== count($routeData)) {
            return null;
        }

        $reflection = new ReflectionClass($routeData[0]);
        if ( ! $reflection->hasMethod($routeData[1])) {
            return null;
        }

        return $reflection->getMethod($routeData[1]);
    }

    /**
     * @throws ReflectionException
     */
    public static function controller(Route $route): ?string
    {
        return self::action($route)?->getDeclaringClass()?->name ?? null;
    }

    private static function containsSerializedClosure(array $action): bool
    {
        if ( ! is_string($action['uses'])) {
            return false;
        }

        $needles = [
            'C:32:"Opis\\Closure\\SerializableClosure',
            'O:47:"Laravel\\SerializableClosure\\SerializableClosure',
            'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure',
        ];

        return Str::startsWith($action['uses'], $needles);
    }

}
