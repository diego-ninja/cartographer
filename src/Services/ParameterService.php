<?php

namespace Ninja\Cartographer\Services;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Ninja\Cartographer\Attributes\Group;
use Ninja\Cartographer\Attributes\Request;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\DTO\Parameters\BodyParameter;
use Ninja\Cartographer\DTO\Parameters\HeaderParameter;
use Ninja\Cartographer\DTO\Parameters\PathParameter;
use Ninja\Cartographer\DTO\Parameters\QueryParameter;
use Ninja\Cartographer\Enums\ParameterFormat;
use Ninja\Cartographer\Formatters\RuleFormatter;
use Ninja\Cartographer\Processors\AttributeProcessor;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;

final readonly class ParameterService
{
    public function __construct(
        private AttributeProcessor $attributeProcessor,
        private RuleFormatter $ruleFormatter,
        private Repository $config,
    ) {}

    public function processRouteParameters(Route $route): ParameterCollection
    {
        $parameters = new ParameterCollection();

        $this->processAttributeParameters($route, $parameters);
        $this->processPathParameters($route, $parameters);
        $this->processRequestParameters($route, $parameters);
        $this->processHeaderParameters($route, $parameters);

        return $parameters;
    }


    private function processAttributeParameters(Route $route, ParameterCollection $parameters): void
    {
        try {
            $action = RouteReflector::action($route);
            if (!$action) {
                return;
            }

            $controller = RouteReflector::controller($route);
            if ($controller) {
                $groupAttribute = $this->attributeProcessor->getGroupAttribute($controller);
                if ($groupAttribute) {
                    $this->processGroupAttribute($groupAttribute, $parameters);
                }
            }

            $requestAttribute = $this->attributeProcessor->getRequestAttribute($action);
            if ($requestAttribute) {
                $this->processRequestAttribute($requestAttribute, $parameters);
            }
        } catch (ReflectionException) {
            return;
        }
    }

    private function processGroupAttribute(Group $group, ParameterCollection $parameters): void
    {
        if ($group->headers) {
            foreach ($group->headers as $key => $value) {
                $parameters->add(new HeaderParameter(
                    name: $key,
                    value: $value,
                    description: sprintf('Group header: %s', $key)
                ));
            }
        }
    }

    private function processRequestAttribute(Request $request, ParameterCollection $parameters): void
    {
        if ($request->params) {
            foreach ($request->params as $name => $description) {
                $parameters->add(new QueryParameter(
                    name: $name,
                    description: $description,
                    rules: [],
                    required: false
                ));
            }
        }

        if ($request->headers) {
            foreach ($request->headers as $key => $value) {
                $parameters->add(new HeaderParameter(
                    name: $key,
                    value: $value,
                    description: sprintf('Request header: %s', $key)
                ));
            }
        }
    }
    private function processPathParameters(Route $route, ParameterCollection $parameters): void
    {
        preg_match_all('/\{([^}]+)}/', $route->uri(), $matches);

        foreach ($matches[1] ?? [] as $param) {
            $isOptional = str_ends_with($param, '?');
            $name = rtrim($param, '?');

            $parameters->add(new PathParameter(
                name: $name,
                description: sprintf('Path parameter: %s', $name),
                required: !$isOptional
            ));
        }
    }

    private function processRequestParameters(Route $route, ParameterCollection $parameters): void
    {
        try {
            $formRequest = $this->resolveFormRequest($route);
            if (!$formRequest) {
                return;
            }

            $rules = $formRequest->rules();
            if (empty($rules)) {
                return;
            }

            $method = strtoupper($route->methods()[0]);
            $isGetRequest = $method === 'GET';

            if ($isGetRequest) {
                $this->processQueryParameters($rules, $parameters);
            } else {
                $this->processBodyParameters($rules, $parameters);
            }
        } catch (ReflectionException) {
            return;
        }
    }

    private function processQueryParameters(array $rules, ParameterCollection $parameters): void
    {
        foreach ($rules as $field => $fieldRules) {
            $parameters->add(new QueryParameter(
                name: $field,
                description: $this->ruleFormatter->format($field, $fieldRules),
                rules: $fieldRules,
                required: $this->isRequired($fieldRules)
            ));
        }
    }

    private function processBodyParameters(array $rules, ParameterCollection $parameters): void
    {
        $structure = $this->buildParameterStructure($rules);

        $parameters->add(new BodyParameter(
            name: 'body',
            structure: $structure,
            description: 'Request body parameters',
            format: ParameterFormat::Json
        ));
    }

    private function processHeaderParameters(Route $route, ParameterCollection $parameters): void
    {
        try {
            $processedHeaders = [];

            $action = RouteReflector::action($route);
            if ($action) {
                $requestAttribute = $this->attributeProcessor->getRequestAttribute($action);
                if ($requestAttribute?->headers) {
                    foreach ($requestAttribute->headers as $key => $value) {
                        $this->addUniqueHeader($parameters, $key, $value, 'Request header', $processedHeaders);
                    }
                }
            }

            $controller = RouteReflector::controller($route);
            if ($controller) {
                $groupAttribute = $this->attributeProcessor->getGroupAttribute($controller);
                if ($groupAttribute?->headers) {
                    foreach ($groupAttribute->headers as $key => $value) {
                        $this->addUniqueHeader($parameters, $key, $value, 'Group header', $processedHeaders);
                    }
                }
            }

            $globalHeaders = $this->config->get('cartographer.headers', []);
            foreach ($globalHeaders as $header) {
                $this->addUniqueHeader(
                    $parameters,
                    $header['key'],
                    $header['value'],
                    $header['description'] ?? 'Global header',
                    $processedHeaders
                );
            }
        } catch (ReflectionException) {
            $this->processGlobalHeaders($parameters);
        }
    }

    private function addUniqueHeader(
        ParameterCollection $parameters,
        string $key,
        string $value,
        string $description,
        array &$processedHeaders
    ): void {
        $normalizedKey = strtolower($key);
        if (isset($processedHeaders[$normalizedKey])) {
            return;
        }

        $parameters->add(new HeaderParameter(
            name: $key,
            value: $value,
            description: $description
        ));

        $processedHeaders[$normalizedKey] = true;
    }

    private function processGlobalHeaders(ParameterCollection $parameters): void
    {
        $processedHeaders = [];
        $globalHeaders = $this->config->get('cartographer.headers', []);

        foreach ($globalHeaders as $header) {
            $this->addUniqueHeader(
                $parameters,
                $header['key'],
                $header['value'],
                $header['description'] ?? 'Global header',
                $processedHeaders
            );
        }
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
                        'description' => $this->ruleFormatter->format($field, $fieldRules)
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

    private function resolveFormRequest(Route $route): ?FormRequest
    {
        try {
            $action = RouteReflector::action($route);
            if (!$action) {
                return null;
            }

            foreach ($action->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type && !$type->isBuiltin()) {
                    $class = $type->getName();
                    if (is_subclass_of($class, FormRequest::class)) {
                        return new $class();
                    }
                }
            }
        } catch (ReflectionException) {
            return null;
        }

        return null;
    }

    private function isRequired(array|string $rules): bool
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        return in_array('required', $rules);
    }

    private function getDefaultValue(array|string $rules): mixed
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
