<?php

namespace Ninja\Cartographer\Processors;

use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Contracts\Exportable;
use Ninja\Cartographer\DTO\Parameters\BodyParameter;
use Ninja\Cartographer\DTO\Parameters\HeaderParameter;
use Ninja\Cartographer\DTO\Parameters\Parameter;
use Ninja\Cartographer\DTO\Parameters\PathParameter;
use Ninja\Cartographer\DTO\Parameters\QueryParameter;
use Ninja\Cartographer\Enums\ParameterFormat;
use Ninja\Cartographer\Enums\ParameterLocation;

final readonly class ParameterProcessor implements Exportable
{
    private ParameterCollection $parameters;

    public function __construct()
    {
        $this->parameters = new ParameterCollection();
    }

    public static function from(string|array|self $parameters): self
    {
        if ($parameters instanceof self) {
            return $parameters;
        }

        if (is_string($parameters)) {
            return self::from(json_decode($parameters, true));
        }

        return (new self())->fromArray($parameters);
    }

    public function addParameter(Parameter $parameter): void
    {
        $this->parameters->put($parameter->name, $parameter);
    }

    public function addParameters(array|ParameterCollection $parameters): void
    {
        $parameters->each(fn(Parameter $parameter) => $this->addParameter($parameter));
    }

    public function forPostman(): array
    {
        $result = [
            'url' => [
                'variable' => [],
                'query' => []
            ],
            'header' => [],
            'body' => null
        ];

        $this->parameters->each(function (Parameter $parameter) use (&$result) {
            match ($parameter->location) {
                ParameterLocation::Path => $result['url']['variable'][] = $parameter->forPostman(),
                ParameterLocation::Query => $result['url']['query'][] = $parameter->forPostman(),
                ParameterLocation::Header => $result['header'][] = $parameter->forPostman(),
                ParameterLocation::Body => $result['body'] = $parameter->forPostman()
            };
        });

        return array_filter($result, function ($value) {
            if (is_array($value)) {
                return !empty(array_filter($value));
            }
            return $value !== null;
        });
    }

    public function forInsomnia(): array
    {
        $result = [
            'parameters' => [],
            'headers' => [],
            'body' => null
        ];

        $this->parameters->each(function (Parameter $parameter) use (&$result) {
            match ($parameter->location) {
                ParameterLocation::Path,
                ParameterLocation::Query => $result['parameters'][] = $parameter->forInsomnia(),
                ParameterLocation::Header => $result['headers'][] = $parameter->forInsomnia(),
                ParameterLocation::Body => $result['body'] = $parameter->forInsomnia()
            };
        });

        return array_filter($result, function ($value) {
            if (is_array($value)) {
                return !empty(array_filter($value));
            }
            return $value !== null;
        });
    }

    public function getParameters(): ParameterCollection
    {
        return $this->parameters;
    }

    public function getParametersByLocation(ParameterLocation $location): ParameterCollection
    {
        return $this->parameters->filter(
            fn(Parameter $parameter) => $parameter->location === $location
        );
    }

    public function hasParametersOfType(ParameterLocation $location): bool
    {
        return $this->parameters->contains(
            fn(Parameter $parameter) => $parameter->location === $location
        );
    }

    public function merge(ParameterProcessor $other): self
    {
        $this->addParameters($other->getParameters());
        return $this;
    }

    public function validate(): bool
    {
        $pathAndQueryParams = $this->parameters->filter(function (Parameter $parameter) {
            return in_array($parameter->location, [
                ParameterLocation::Path,
                ParameterLocation::Query
            ]);
        });

        $names = $pathAndQueryParams->pluck('name');
        return $names->count() === $names->unique()->count();
    }

    public function buildUrlTemplate(): string
    {
        $pathParams = $this->getParametersByLocation(ParameterLocation::Path);
        $queryParams = $this->getParametersByLocation(ParameterLocation::Query);

        $url = '{{base_url}}';

        $pathParams->each(function (PathParameter $param) use (&$url) {
            $url .= '/{'.$param->name.'}';
        });

        if ($queryParams->isNotEmpty()) {
            $url .= '?' . $queryParams->map(function (QueryParameter $param) {
                    return $param->name . '=' . ($param->value ?? '');
                })->join('&');
        }

        return $url;
    }

    public function buildDescription(): string
    {
        $description = [];

        $pathParams = $this->getParametersByLocation(ParameterLocation::Path);
        if ($pathParams->isNotEmpty()) {
            $description[] = "### URL Parameters\n";
            $pathParams->each(function (PathParameter $param) use (&$description) {
                $description[] = sprintf(
                    "- `%s`: %s%s",
                    $param->name,
                    $param->description,
                    $param->required ? ' (Required)' : ''
                );
            });
        }

        $queryParams = $this->getParametersByLocation(ParameterLocation::Query);
        if ($queryParams->isNotEmpty()) {
            $description[] = "\n### Query Parameters\n";
            $queryParams->each(function (QueryParameter $param) use (&$description) {
                $description[] = sprintf(
                    "- `%s`: %s%s",
                    $param->name,
                    $param->description,
                    $param->required ? ' (Required)' : ''
                );
            });
        }

        if ($this->hasParametersOfType(ParameterLocation::Body)) {
            $description[] = "\n### Body Parameters\n";
            $this->getParametersByLocation(ParameterLocation::Body)
                ->each(function (BodyParameter $param) use (&$description) {
                    $description[] = sprintf(
                        "- `%s`: %s%s",
                        $param->name,
                        $param->description,
                        $param->required ? ' (Required)' : ''
                    );
                });
        }

        return implode("\n", $description);
    }

    public function fromArray(array $parameters): self
    {
        foreach ($parameters as $param) {
            $parameter = match($param['location'] ?? ParameterLocation::Query) {
                ParameterLocation::Path => new PathParameter(
                    name: $param['name'],
                    description: $param['description'] ?? '',
                    required: $param['required'] ?? true,
                    example: $param['example'] ?? null,
                    value: $param['value'] ?? null
                ),
                ParameterLocation::Query => new QueryParameter(
                    name: $param['name'],
                    description: $param['description'] ?? '',
                    rules: $param['rules'] ?? [],
                    required: $param['required'] ?? false,
                    example: $param['example'] ?? null,
                    value: $param['value'] ?? null
                ),
                ParameterLocation::Body => new BodyParameter(
                    name: $param['name'],
                    structure: $param['structure'] ?? [],
                    description: $param['description'] ?? '',
                    rules: $param['rules'] ?? [],
                    required: $param['required'] ?? false,
                    example: $param['example'] ?? null,
                    format: isset($param['format']) ? ParameterFormat::from($param['format']) : null
                ),
                ParameterLocation::Header => new HeaderParameter(
                    name: $param['name'],
                    value: $param['value'] ?? '',
                    description: $param['description'] ?? '',
                    required: $param['required'] ?? false
                )
            };

            $this->addParameter($parameter);
        }

        return $this;
    }
}
