<?php

namespace Ninja\Cartographer\Processors;

use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\DTO\Parameter;
use Ninja\Cartographer\Enums\ParameterType;
use Illuminate\Foundation\Http\FormRequest;
use ReflectionParameter;

final readonly class FormDataProcessor
{
    public function process($reflectionMethod, array $formdata = []): ParameterCollection
    {
        $parameters = new ParameterCollection();

        /** @var ReflectionParameter $rulesParameter */
        $rulesParameter = collect($reflectionMethod->getParameters())
            ->first(function ($value) {
                $value = $value->getType();
                return $value && is_subclass_of($value->getName(), FormRequest::class);
            });

        if ($rulesParameter) {
            /** @var FormRequest $class */
            $class = new ($rulesParameter->getType()->getName());
            $classRules = method_exists($class, 'rules') ? $class->rules() : [];

            foreach ($classRules as $fieldName => $rule) {
                if (is_string($rule)) {
                    $rule = preg_split('/\s*\|\s*/', $rule);
                }

                $parameters->add(new Parameter(
                    name: $fieldName,
                    value: $formdata[$fieldName] ?? '',
                    description: '',
                    rules: $rule,
                    type: ParameterType::QUERY
                ));

                if (is_array($rule) && in_array('confirmed', $rule)) {
                    $confirmationField = $fieldName . '_confirmation';
                    $parameters->add(new Parameter(
                        name: $confirmationField,
                        value: $formdata[$confirmationField] ?? '',
                        description: '',
                        rules: $rule,
                        type: ParameterType::QUERY
                    ));
                }
            }
        }

        return $parameters;
    }
}
