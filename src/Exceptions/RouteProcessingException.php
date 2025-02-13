<?php

namespace Ninja\Cartographer\Exceptions;

class RouteProcessingException extends CartographerException
{
    public static function invalidRouteAction(string $action): self
    {
        return new self(
            sprintf('Invalid route action: %s', $action),
        );
    }

    public static function missingMiddleware(string $route, string $middleware): self
    {
        return new self(
            sprintf('Route "%s" is missing required middleware: %s', $route, $middleware),
        );
    }

    public static function invalidFormRequest(string $route, string $error): self
    {
        return new self(
            sprintf('Invalid form request in route "%s": %s', $route, $error),
        );
    }
}
