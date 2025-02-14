<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\DTO\Parameters\Parameter;
use Ninja\Cartographer\Enums\ParameterLocation;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;
use ReflectionParameter;

final readonly class FormDataProcessor
{
    /**
     * @throws ReflectionException
     */
    public function process(Route $route, array $formdata = []): ParameterCollection
    {
        $parameters = new ParameterCollection();
        $rfx = RouteReflector::method($route);

        /** @var ReflectionParameter $rulesParameter */
        $rulesParameter = collect($rfx->getParameters())
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
                    type: ParameterLocation::Query,
                ));

                if (is_array($rule) && in_array('confirmed', $rule)) {
                    $confirmationField = $fieldName . '_confirmation';
                    $parameters->add(new Parameter(
                        name: $confirmationField,
                        value: $formdata[$confirmationField] ?? '',
                        description: '',
                        rules: $rule,
                        type: ParameterLocation::Query,
                    ));
                }
            }
        }

        return $parameters;
    }
}
