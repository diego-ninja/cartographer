<?php

namespace Ninja\Cartographer\Mappers;

use Illuminate\Foundation\Http\FormRequest;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\DTO\Parameters\BodyParameter;
use Ninja\Cartographer\DTO\Parameters\QueryParameter;
use Ninja\Cartographer\Enums\ParameterFormat;

final readonly class RequestParameterMapper extends ParameterMapper
{
    public function __construct(
        private FormRequest $formRequest,
        private ParameterFormat $format = ParameterFormat::Json,
        private bool $forQuery = false
    ) {}

    public function map(): ParameterCollection
    {
        $parameters = [];
        $rules = $this->formRequest->rules();

        if (empty($rules)) {
            return new ParameterCollection();
        }

        $structure = $this->buildParameterStructure($rules);

        if ($this->forQuery) {
            foreach ($structure as $field => $details) {
                $parameters[] = new QueryParameter(
                    name: $field,
                    description: $this->getDescriptionFromRules($details['rules'] ?? []),
                    rules: $details['rules'] ?? [],
                    required: $this->isRequired($details['rules'] ?? [])
                );
            }
        } else {
            $parameters[] = new BodyParameter(
                name: 'body',
                structure: $structure,
                description: 'Request body parameters',
                format: $this->format
            );
        }

        return new ParameterCollection($parameters);
    }

    private function buildParameterStructure(array $rules): array
    {
        $structure = [];

        foreach ($rules as $field => $fieldRules) {
            $parts = explode('.', $field);
            $current = &$structure;

            foreach ($parts as $i => $part) {
                if ($part === '*') {
                    continue;
                }

                if ($i === count($parts) - 1) {
                    $current[$part] = [
                        'value' => $this->getDefaultValue($fieldRules),
                        'rules' => $fieldRules,
                        'description' => $this->getDescriptionFromRules($fieldRules)
                    ];
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }
        }

        return $structure;
    }

    private function getDescriptionFromRules(array|string $rules): string
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $descriptions = [];
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                if (str_contains($rule, ':')) {
                    [$ruleName, $params] = explode(':', $rule, 2);
                    $descriptions[] = $this->formatRule($ruleName, $params);
                } else {
                    $descriptions[] = $this->formatRule($rule);
                }
            }
        }

        return implode('. ', $descriptions);
    }

    private function formatRule(string $rule, ?string $params = null): string
    {
        return match($rule) {
            'required' => 'Required',
            'string' => 'Must be a string',
            'integer' => 'Must be an integer',
            'numeric' => 'Must be numeric',
            'array' => 'Must be an array',
            'boolean' => 'Must be a boolean',
            'email' => 'Must be a valid email',
            'min' => "Minimum value: $params",
            'max' => "Maximum value: $params",
            'confirmed' => 'Must be confirmed',
            'in' => "Must be one of: $params",
            default => ucfirst($rule)
        };
    }

    protected function getDefaultValue(array|string $rules): mixed
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;

        return match(true) {
            in_array('array', $rules) => [],
            in_array('boolean', $rules) => false,
            in_array('integer', $rules) => 0,
            in_array('numeric', $rules) => 0.0,
            default => ''
        };
    }
}
