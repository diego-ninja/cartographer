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


    public function toInsomniaFormat(): array
    {
        return [
            'type' => $this->getType(),
            'token' => '{{ token }}',
            'prefix' => $this->prefix(),
            'disabled' => false,
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
                    'type' => 'string',
                ],
            ],
        ];
    }
}
