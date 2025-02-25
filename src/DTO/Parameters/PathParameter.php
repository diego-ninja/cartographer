<?php

namespace Ninja\Cartographer\DTO\Parameters;

use Ninja\Cartographer\Enums\ParameterLocation;

final readonly class PathParameter extends Parameter
{
    public function __construct(
        string $name,
        string $description,
        bool $required = true,
        mixed $value = null,
    ) {
        parent::__construct(
            name: $name,
            value: $value,
            description: $description,
            required: $required,
            location: ParameterLocation::Path
        );
    }
    public function forPostman(): array
    {
        return [
            'key' => $this->name,
            'value' => $this->value ?? '',
            'description' => $this->description,
            'type' => 'string',
            'required' => $this->required
        ];
    }

    public function forInsomnia(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value ?? '',
            'description' => $this->description,
            'disabled' => false
        ];
    }
}
