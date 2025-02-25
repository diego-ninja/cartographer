<?php

namespace Ninja\Cartographer\Authentication\Strategy;

use Ninja\Cartographer\Contracts\AuthenticationStrategy;
use Ninja\Cartographer\Contracts\Exportable;

abstract readonly class AbstractAuthStrategy implements AuthenticationStrategy, Exportable
{
    public function __construct(
        protected ?string $token = null,
    ) {}

    abstract public function prefix(): string;

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
            'type' => 'string',
        ];
    }

    public function forPostman(): array
    {
        return [
            'type' => $this->getType(),
            $this->getType() => [
                [
                    'key' => 'token',
                    'value' => $this->getToken(),
                    'type' => 'string',
                ],
            ],
        ];
    }

    public function forInsomnia(): array
    {
        return [
            'type' => $this->getType(),
            'token' => $this->getToken(),
            'prefix' => $this->prefix(),
            'disabled' => false,
        ];
    }
}
