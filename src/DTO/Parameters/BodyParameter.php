<?php

namespace Ninja\Cartographer\DTO\Parameters;

use Ninja\Cartographer\Enums\ParameterFormat;
use Ninja\Cartographer\Enums\ParameterLocation;

final readonly class BodyParameter extends Parameter
{
    private array $structure;

    public function __construct(
        string $name,
        array $structure,
        string $description,
        array $rules = [],
        bool $required = false,
        ?string $example = null,
        ?ParameterFormat $format = ParameterFormat::Json,
    ) {
        parent::__construct(
            name: $name,
            value: null,
            description: $description,
            rules: $rules,
            required: $required,
            example: $example,
            location: ParameterLocation::Body,
            format: $format
        );

        $this->structure = $structure;
    }

    public function forPostman(): array
    {
        return match($this->format) {
            ParameterFormat::Json => [
                'mode' => 'raw',
                'raw' => json_encode($this->structure, JSON_PRETTY_PRINT),
                'options' => [
                    'raw' => [
                        'language' => 'json'
                    ]
                ]
            ],
            ParameterFormat::FormData => [
                'mode' => 'formdata',
                'formdata' => $this->buildFormDataStructure()
            ],
            ParameterFormat::UrlEncoded => [
                'mode' => 'urlencoded',
                'urlencoded' => $this->buildUrlEncodedStructure()
            ],
            default => [
                'mode' => 'raw',
                'raw' => json_encode($this->structure)
            ]
        };
    }

    public function forInsomnia(): array
    {
        return match($this->format) {
            ParameterFormat::Json => [
                'mimeType' => ParameterFormat::Json->value,
                'text' => json_encode($this->structure, JSON_PRETTY_PRINT)
            ],
            ParameterFormat::FormData => [
                'mimeType' => ParameterFormat::FormData->value,
                'params' => $this->buildInsomniaFormDataStructure()
            ],
            ParameterFormat::UrlEncoded => [
                'mimeType' => ParameterFormat::UrlEncoded->value,
                'params' => $this->buildInsomniaUrlEncodedStructure()
            ],
            default => [
                'mimeType' => 'text/plain',
                'text' => json_encode($this->structure)
            ]
        };
    }

    private function buildFormDataStructure(): array
    {
        return $this->flattenStructure($this->structure);
    }

    private function buildUrlEncodedStructure(): array
    {
        return $this->flattenStructure($this->structure);
    }

    private function buildInsomniaFormDataStructure(): array
    {
        return array_map(
            fn($item) => [
                'name' => $item['key'],
                'value' => $item['value'],
                'description' => $item['description'] ?? '',
                'type' => 'text'
            ],
            $this->flattenStructure($this->structure)
        );
    }

    private function buildInsomniaUrlEncodedStructure(): array
    {
        return array_map(
            fn($item) => [
                'name' => $item['key'],
                'value' => $item['value'],
                'description' => $item['description'] ?? ''
            ],
            $this->flattenStructure($this->structure)
        );
    }

    private function flattenStructure(array $structure, string $prefix = ''): array
    {
        $result = [];

        foreach ($structure as $key => $value) {
            $fullKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value) && !isset($value['key'])) {
                $result = array_merge(
                    $result,
                    $this->flattenStructure($value, $fullKey)
                );
            } else {
                $result[] = [
                    'key' => $fullKey,
                    'value' => is_array($value) ? $value['value'] : $value,
                    'description' => is_array($value) ? ($value['description'] ?? '') : '',
                    'type' => 'text'
                ];
            }
        }

        return $result;
    }
}
