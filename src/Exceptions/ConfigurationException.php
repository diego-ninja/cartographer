<?php

namespace Ninja\Cartographer\Exceptions;

final class ConfigurationException extends CartographerException
{
    public static function invalidAuthMethod(string $method): self
    {
        return new self(
            sprintf('Invalid authentication method "%s". Available methods: bearer, basic, apikey', $method)
        );
    }

    public static function invalidExportFormat(string $format): self
    {
        return new self(
            sprintf('Invalid export format "%s". Available formats: postman, insomnia, bruno', $format)
        );
    }

    public static function invalidBodyMode(string $mode): self
    {
        return new self(
            sprintf('Invalid body mode "%s". Available modes: raw, formdata, urlencoded, file, graphql, none', $mode)
        );
    }

    public static function missingRequiredConfig(string $key): self
    {
        return new self(
            sprintf('Missing required configuration key: %s', $key)
        );
    }
}
