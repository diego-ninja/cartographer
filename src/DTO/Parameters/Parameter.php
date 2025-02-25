<?php

namespace Ninja\Cartographer\DTO\Parameters;

use JsonSerializable;
use Ninja\Cartographer\Contracts\Exportable;
use Ninja\Cartographer\Enums\ParameterFormat;
use Ninja\Cartographer\Enums\ParameterLocation;

readonly class Parameter implements JsonSerializable, Exportable
{
    public function __construct(
        public string            $name,
        public mixed             $value,
        public ?string           $description = null,
        public array             $rules = [],
        public array             $metadata = [],
        public bool              $required = false,
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
            metadata: $data['metadata'] ?? [],
            required: $data['required'] ?? false,
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
            'metadata' => $this->metadata,
            'required' => $this->required,
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

    public function forPostman(): array
    {
        return match($this->location) {
            ParameterLocation::Path => [
                'key' => $this->name,
                'value' => $this->value ?? '',
                'description' => $this->description,
                'type' => 'string',
                'required' => $this->required
            ],
            ParameterLocation::Query => [
                'key' => $this->name,
                'value' => $this->value ?? '',
                'description' => $this->description,
                'disabled' => false
            ],
            ParameterLocation::Header => [
                'key' => $this->name,
                'value' => $this->value ?? '',
                'type' => 'text'
            ],
            default => []
        };
    }

    public function forInsomnia(): array
    {
        return match($this->location) {
            ParameterLocation::Path,
            ParameterLocation::Query => [
                'name' => $this->name,
                'value' => $this->value ?? '',
                'description' => $this->description,
                'disabled' => false
            ],
            ParameterLocation::Header => [
                'name' => $this->name,
                'value' => $this->value ?? '',
                'description' => $this->description
            ],
            default => []
        };
    }
}
