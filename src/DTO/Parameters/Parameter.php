<?php

namespace Ninja\Cartographer\DTO\Parameters;

use JsonSerializable;
use Ninja\Cartographer\Contracts\Exportable;
use Ninja\Cartographer\Enums\ParameterFormat;
use Ninja\Cartographer\Enums\ParameterLocation;

abstract readonly class Parameter implements JsonSerializable, Exportable
{
    public function __construct(
        public string            $name,
        public string            $value,
        public ?string           $description = null,
        public array             $rules = [],
        public bool              $required = false,
        public ?string           $example = null,
        public ParameterLocation $location = ParameterLocation::Query,
        public ?ParameterFormat   $format = null,
    ) {}

    public static function from(string|array|Parameter $data): Parameter
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_string($data)) {
            return self::from(json_decode($data, true));
        }

        return new static(
            name: $data['key'],
            value: $data['value'],
            description: $data['description'] ?? null,
            rules: $data['rules'] ?? [],
            required: $data['required'] ?? false,
            example: $data['example'] ?? null,
            location: ParameterLocation::from($data['location'] ?? 'query'),
            format: $data['format'] ? ParameterFormat::from($data['format']) : null,
        );
    }

    public function array(): array
    {
        return [
            'key' => $this->name,
            'value' => $this->value,
            'description' => $this->description,
            'rules' => $this->rules,
            'required' => $this->required,
            'example' => $this->example,
            'location' => $this->location->value,
            'format' => $this->format?->value,
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
