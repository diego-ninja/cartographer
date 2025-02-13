<?php

namespace Ninja\Cartographer\Exceptions;

final class AuthenticationException extends CartographerException
{
    public static function invalidToken(string $details): self
    {
        return new self(
            sprintf('Invalid authentication token: %s', $details),
        );
    }

    public static function missingAuthenticationMiddleware(string $route): self
    {
        return new self(
            sprintf('Route "%s" requires authentication but middleware is not configured', $route),
        );
    }
}
