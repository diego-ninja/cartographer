<?php

namespace Ninja\Cartographer\Mappers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\DTO\Parameters\BodyParameter;
use Ninja\Cartographer\DTO\Parameters\QueryParameter;
use Ninja\Cartographer\Enums\ParameterFormat;
use Str;

final readonly class RequestParameterMapper extends ParameterMapper
{
    public function __construct(
        private FormRequest $formRequest,
        private ParameterFormat $format = ParameterFormat::Json,
        private bool $forQuery = false
    ) {}

    public function map(): Collection
    {
        $parameters = [];
        $groupedRules = $this->groupRulesByPrefix($this->formRequest->rules());

        foreach ($groupedRules as $prefix => $ruleGroup) {
            if ($this->forQuery) {
                $parameters[] = $this->createQueryParameter($prefix, $ruleGroup);
            } else {
                $parameters[] = $this->createBodyParameter($prefix, $ruleGroup);
            }
        }

        return ParameterCollection::from($parameters);
    }

    private function groupRulesByPrefix(?array $rules): array
    {
        $groups = [];

        if (!$rules) {
            return $groups;
        }

        foreach ($rules as $field => $rule) {
            if (str_contains($field, '.*.')) {
                $prefix = Str::before($field, '.*.');
                $suffix = Str::after($field, '.*.');
                $groups[$prefix]['array_fields'][$suffix] = $rule;
            } else {
                $groups[$field]['rules'] = $rule;
            }
        }

        return $groups;
    }

    private function createQueryParameter(string $name, array $ruleGroup): QueryParameter
    {
        return new QueryParameter(
            name: $name,
            description: $this->getDescriptionFromRules($ruleGroup['rules'] ?? []),
            rules: $ruleGroup['rules'] ?? [],
            required: $this->isRequired($ruleGroup['rules'] ?? [])
        );
    }

    private function createBodyParameter(string $name, array $ruleGroup): BodyParameter
    {
        $structure = [];

        if (isset($ruleGroup['array_fields'])) {
            $itemStructure = array_map(function ($rules) {
                return [
                    'value' => $this->getDefaultValue($rules),
                    'description' => $this->getDescriptionFromRules($rules),
                    'required' => $this->isRequired($rules)
                ];
            }, $ruleGroup['array_fields']);

            $structure = [$itemStructure];
        } else {
            $structure[$name] = $this->getDefaultValue($ruleGroup['rules'] ?? []);
        }

        return new BodyParameter(
            name: $name,
            structure: $structure,
            description: $this->getDescriptionFromRules($ruleGroup['rules'] ?? []),
            rules: $ruleGroup['rules'] ?? [],
            required: $this->isRequired($ruleGroup['rules'] ?? []),
            format: $this->format
        );
    }

    private function getDescriptionFromRules(array|string $rules): string
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        $description = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                if (str_contains($rule, ':')) {
                    [$ruleName, $params] = explode(':', $rule, 2);
                    $description[] = $this->formatRule($ruleName, $params);
                } else {
                    $description[] = $this->formatRule($rule);
                }
            }
        }

        return implode('. ', $description);
    }

    private function formatRule(string $rule, ?string $params = null): string
    {
        return match($rule) {
            'required' => 'Required',
            'string' => 'Must be a string',
            'integer' => 'Must be an integer',
            'numeric' => 'Must be numeric',
            'min' => "Minimum value: $params",
            'max' => "Maximum value: $params",
            'email' => 'Must be a valid email',
            'array' => 'Must be an array',
            default => $rule
        };
    }
}
