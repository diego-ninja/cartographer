<?php

namespace Ninja\Cartographer\DTO\Parameters;

use Ninja\Cartographer\Enums\ParameterLocation;

final readonly class HeaderParameter extends Parameter
{
    public function __construct(
        string $name,
        string $value,
        string $description = '',
        bool $required = false
    ) {
        parent::__construct(
            name: $name,
            value: $value,
            description: $description,
            required: $required,
            location: ParameterLocation::Header
        );
    }

    public function forPostman(): array
    {
        return [
            'key' => $this->name,
            'value' => $this->value,
            'description' => $this->description,
            'type' => 'text'
        ];
    }

    public function forInsomnia(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'description' => $this->description,
            'disabled' => false
        ];
    }
}
