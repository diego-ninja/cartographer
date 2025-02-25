<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Enums\BodyMode;
use Ninja\Cartographer\Enums\Method;
use Ninja\Cartographer\Enums\ParameterFormat;
use Ninja\Cartographer\Mappers\RequestParameterMapper;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;
use ReflectionParameter;

final readonly class FormDataProcessor
{
    /**
     * @throws ReflectionException
     */
    public function process(Route $route, Method $method, array $formdata = []): ParameterCollection
    {
        $forQuery = $method === Method::GET;
        $mode = $forQuery ? BodyMode::Raw : BodyMode::from(
            config('cartographer.body_mode', BodyMode::FormData->value)
        );

        $format = $mode === BodyMode::FormData ? ParameterFormat::FormData : ParameterFormat::Raw;

        $parameters = new ParameterCollection();
        $action = RouteReflector::action($route);

        if (!$action) {
            return $parameters;
        }

        if (!$this->hasFormRequest($route)) {
            return $parameters;
        }

        /** @var FormRequest $formRequest */
        $formRequest = $this->getFormRequest($route);

        if (!method_exists($formRequest, 'rules') || empty($formRequest->rules())) {
            return $parameters;
        }

        $mapper = new RequestParameterMapper(
            formRequest: $formRequest,
            format: $format,
            forQuery: $forQuery
        );

        return $mapper->map();
    }

    public function hasFormRequest(Route $route): bool
    {
        try {
            $action = RouteReflector::action($route);
            if (!$action) {
                return false;
            }

            return collect($action->getParameters())
                ->contains(function (ReflectionParameter $param) {
                    $type = $param->getType();
                    return $type && !$type->isBuiltin()
                        && class_exists($type->getName())
                        && is_subclass_of($type->getName(), FormRequest::class);
                });

        } catch (ReflectionException) {
            return false;
        }
    }

    public function getFormRequest(Route $route): ?FormRequest
    {
        try {
            $action = RouteReflector::action($route);
            if (!$action) {
                return null;
            }

            $formRequestParam = collect($action->getParameters())
                ->first(function (ReflectionParameter $param) {
                    $type = $param->getType();
                    return $type && !$type->isBuiltin()
                        && class_exists($type->getName())
                        && is_subclass_of($type->getName(), FormRequest::class);
                });

            if (!$formRequestParam) {
                return null;
            }

            $formRequestClass = $formRequestParam->getType()->getName();
            return new $formRequestClass();

        } catch (ReflectionException) {
            return null;
        }
    }

    public function getRules(FormRequest $formRequest): array
    {
        if (!method_exists($formRequest, 'rules')) {
            return [];
        }

        return $formRequest->rules();
    }

    public function hasRule(FormRequest $formRequest, string $field, string $rule): bool
    {
        $rules = $this->getRules($formRequest);

        if (!isset($rules[$field])) {
            return false;
        }

        $fieldRules = is_array($rules[$field]) ? $rules[$field] : explode('|', $rules[$field]);

        return collect($fieldRules)
            ->contains(function ($ruleItem) use ($rule) {
                if (is_string($ruleItem)) {
                    return str_starts_with($ruleItem, $rule);
                }
                return false;
            });
    }

    public function getRequiredFields(FormRequest $formRequest): array
    {
        $rules = $this->getRules($formRequest);

        return collect($rules)
            ->filter(function ($fieldRules, $field) {
                $rules = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
                return in_array('required', $rules);
            })
            ->keys()
            ->all();
    }
}
