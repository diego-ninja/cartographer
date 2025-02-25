<?php

namespace Ninja\Cartographer\Formatters;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationRuleParser;

final readonly class RuleFormatter
{
    public function __construct(
        private Repository $config,
    ) {}

    /**
     * @param string $attribute
     * @param ValidationRule|array|string $rules
     * @return string
     */
    public function format(string $attribute, ValidationRule|array|string $rules): string
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        if ($rules instanceof ValidationRule) {
            $rules = [$rules];
        }

        if (!$this->config->get('cartographer.rules_to_human_readable')) {
            $filteredRules = array_filter($rules, fn($rule) => !($rule instanceof ValidationRule));
            return implode(', ', $filteredRules);
        }

        $validator = Validator::make([], [$attribute => implode('|', $this->normalizeRules($rules))]);

        $messages = [];
        foreach ($rules as $rule) {
            if ($rule instanceof ValidationRule) {
                $messages[] = $this->formatValidationRule($rule);
                continue;
            }

            [$ruleName, $parameters] = ValidationRuleParser::parse($rule);
            $validator->addFailure($attribute, $ruleName, $parameters);
        }

        $validatorMessages = $validator->getMessageBag()->get($attribute);
        $messages = array_merge($messages, $validatorMessages);

        return $this->processSpecialMessages($messages);
    }

    private function normalizeRules(array $rules): array
    {
        return array_map(function ($rule) {
            if ($rule instanceof ValidationRule) {
                return (string) $rule;
            }
            return $rule;
        }, $rules);
    }

    private function formatValidationRule(ValidationRule $rule): string
    {
        if (method_exists($rule, '__toString')) {
            return (string) $rule;
        }

        $className = class_basename($rule);
        return sprintf("Must pass %s validation", $className);
    }

    private function processSpecialMessages(array $messages): string
    {
        $processed = array_map(function ($message) {
            return match ($message) {
                'validation.nullable' => '(Optional)',
                'validation.sometimes' => '(Sometimes)',
                'validation.required' => '(Required)',
                default => $message,
            };
        }, $messages);

        return implode(', ', array_filter($processed));
    }
}
