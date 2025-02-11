<?php

namespace Ninja\Cartographer\Exceptions;

final class ExportException extends CartographerException
{
    public static function failedToCreateDirectory(string $path, string $error): self
    {
        return new self(
            sprintf('Failed to create directory "%s". Error: %s', $path, $error)
        );
    }

    public static function failedToWriteFile(string $path, string $error): self
    {
        return new self(
            sprintf('Failed to write file "%s". Error: %s', $path, $error)
        );
    }

    public static function invalidStructure(string $details): self
    {
        return new self(
            sprintf('Invalid collection structure: %s', $details)
        );
    }
}
