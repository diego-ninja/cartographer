<?php

namespace Ninja\Cartographer\Authentication\Strategy;

final readonly class BearerStrategy extends AbstractAuthStrategy
{
    public function prefix(): string
    {
        return 'Bearer';
    }

    public function getType(): string
    {
        return 'bearer';
    }


    public function forInsomnia(): array
    {
        return [
            'type' => $this->getType(),
            'token' => '{{ token }}',
            'prefix' => $this->prefix(),
            'disabled' => false,
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
}
