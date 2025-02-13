<?php

namespace Ninja\Cartographer\Exceptions;

use Throwable;

final class ValidationException extends CartographerException
{
    protected array $errors = [];

    public function __construct(string $message = "", array $errors = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public static function invalidResourceId(string $id, string $prefix): self
    {
        return new self(
            sprintf('Invalid resource ID "%s". Must start with "%s_"', $id, $prefix),
        );
    }

    public static function duplicateResourceId(string $id): self
    {
        return new self(
            sprintf('Duplicate resource ID: %s', $id),
        );
    }

    public static function invalidParameter(string $parameter, string $details): self
    {
        return new self(
            sprintf('Invalid parameter "%s": %s', $parameter, $details),
        );
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
