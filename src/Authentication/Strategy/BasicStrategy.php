<?php

namespace Ninja\Cartographer\Authentication\Strategy;

final readonly class BasicStrategy extends AbstractAuthStrategy
{
    public function prefix(): string
    {
        return 'Basic';
    }

    public function toArray(): array
    {
        return [
            'key' => 'Authorization',
            'value' => sprintf('%s %s', $this->prefix(), base64_encode($this->getToken())),
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
                    'value' => $this->getToken(), // Token sin codificar para las variables
                    'type' => 'string'
                ]
            ]
        ];
    }

    public function toInsomniaFormat(): array
    {
        return [
            'type' => $this->getType(),
            'token' => '{{ token }}', // Siempre usamos la variable en Insomnia
            'prefix' => $this->prefix(),
            'disabled' => false
        ];
    }

    public function getType(): string
    {
        return 'basic';
    }

}
