<?php

namespace Ninja\Cartographer\Authentication\Strategy;

use Ninja\Cartographer\Contracts\AuthenticationStrategy;

abstract readonly class AbstractAuthStrategy implements AuthenticationStrategy
{
    public function __construct(
        protected ?string $token = null
    ) {}

    public function getToken(): string
    {
        return $this->token ?? '{{token}}';
    }

    public function getType(): string
    {
        return mb_strtolower(class_basename($this));
    }

    public function toArray(): array
    {
        return [
            'key' => 'Authorization',
            'value' => sprintf('%s %s', $this->prefix(), $this->getToken()),
            'type' => 'string'
        ];
    }

    public function toPostmanFormat(): array
    {
        return [
            'type' => $this->getType(),
            $this->getType() => [
                [
                    'key' => 'token',
                    'value' => $this->getToken(),
                    'type' => 'string'
                ]
            ]
        ];
    }

    public function toInsomniaFormat(): array
    {
        return [
            'type' => $this->getType(),
            'token' => $this->getToken(),
            'prefix' => $this->prefix(),
            'disabled' => false
        ];
    }

    abstract public function prefix(): string;
}
