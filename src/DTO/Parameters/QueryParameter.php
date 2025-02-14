<?php

namespace Ninja\Cartographer\DTO\Parameters;

final readonly class QueryParameter extends Parameter
{
    public function __construct(
        string $name,
        string $description,
        array $rules = [],
        bool $required = false,
        ?string $example = null,
        mixed $value = null,
    ) {
        parent::__construct(
            name: $name,
            value: $value,
            description: $description,
            rules: $rules,
            required: $required,
            example: $example
        );
    }

    public function forPostman(): array
    {
        return [
            'key' => $this->name,
            'value' => $this->value ?? '',
            'description' => $this->description,
            'disabled' => false,
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
