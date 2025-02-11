<?php

namespace Ninja\Cartographer\Authentication\Strategy;

final readonly class ApiKeyStrategy extends AbstractAuthStrategy
{
    public function __construct(
        protected ?string $token = null,
        protected string  $prefix = 'ApiKey'
    ) {
        parent::__construct($token);
    }

    public function getType(): string
    {
        return 'apikey';
    }

    public function prefix(): string
    {
        return $this->prefix;
    }
}
