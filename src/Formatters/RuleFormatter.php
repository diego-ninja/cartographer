<?php

namespace Ninja\Cartographer\Formatters;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationRuleParser;

final readonly class RuleFormatter
{
    public function __construct(
        private Repository $config,
    ) {}

    public function format(string $attribute, array|Rule $rules): string
    {
        if ( ! $this->config->get('cartographer.rules_to_human_readable')) {
            foreach ($rules as $i => $rule) {
                if (is_subclass_of($rule, ValidationRule::class)) {
                    unset($rules[$i]);
                }
            }

            return is_array($rules) ? implode(', ', $rules) : $this->stringify($rules);
        }

        if (is_object($rules)) {
            $rules = [$this->stringify($rules)];
        }

        if (is_array($rules) && ! empty($rules)) {
            $validator = Validator::make([], [$attribute => implode('|', $rules)]);

            foreach ($rules as $rule) {
                [$rule, $parameters] = ValidationRuleParser::parse($rule);

                $validator->addFailure($attribute, $rule, $parameters);
            }

            $messages = $validator->getMessageBag()->toArray()[$attribute];

            if (is_array($messages)) {
                $messages = $this->handleEdgeCases($messages);
            }

            return implode(', ', is_array($messages) ? $messages : $messages->toArray());
        }

        return '';
    }

    private function handleEdgeCases(array $messages): array
    {
        foreach ($messages as $key => $message) {
            if ('validation.nullable' === $message) {
                $messages[$key] = '(Nullable)';

                continue;
            }

            if ('validation.sometimes' === $message) {
                $messages[$key] = '(Optional)';
            }
        }

        return $messages;
    }

    private function stringify(object $rule): string
    {
        if ($rule instanceof Rule && method_exists($rule, '__toString')) {
            return (string) $rule;
        }

        return '';
    }
}
