<?php

namespace Ninja\Cartographer\DTO;

use JsonSerializable;
use Ninja\Cartographer\Enums\ParameterType;

final readonly class Parameter implements JsonSerializable
{
    public function __construct(
        public string  $name,
        public string  $value,
        public ?string $description = null,
        public array $rules = [],
        public bool $disabled = false,
        public ParameterType  $type = ParameterType::QUERY,
    ) {}

    public static function from(string|array|Parameter $data): Parameter
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_string($data)) {
            return self::from(json_decode($data, true));
        }

        return new self(
            name: $data['key'],
            value: $data['value'],
            description: $data['description'] ?? null,
            rules: $data['rules'] ?? [],
            disabled: $data['disabled'] ?? false,
            type: ParameterType::from($data['type']),
        );
    }

    public function array(): array
    {
        return [
            'key' => $this->name,
            'value' => $this->value,
            'description' => $this->description,
            'rules' => $this->rules,
            'type' => $this->type->value,
        ];
    }

    public function json(): string
    {
        return json_encode($this->array());
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }
}
