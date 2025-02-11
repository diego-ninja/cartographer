<?php

namespace Ninja\Cartographer\Collections;

use Ninja\Cartographer\Attributes\Request;
use Ninja\Cartographer\DTO\Parameter;
use Ninja\Cartographer\Enums\ParameterType;
use Ninja\Cartographer\Formatters\RuleFormatter;
use Ninja\Cartographer\Processors\FormDataProcessor;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;

final class ParameterCollection extends Collection
{
    /**
     * @param array<Parameter> $parameters
     */
    public static function from(array $parameters): ParameterCollection
    {
        return new self(array_map(fn(array|Parameter $parameter) => Parameter::from($parameter), $parameters));
    }

    public function byType(ParameterType $type): ParameterCollection
    {
        return $this->filter(fn(Parameter $parameter) => $parameter->type === $type);
    }

    public function fromRoute(Route $route): self
    {
        preg_match_all('/\{([^}]+)}/', $route->uri(), $matches);
        foreach ($matches[1] as $param) {
            $this->add(new Parameter(
                name: $param,
                value: '',
                description: '',
                type: ParameterType::PATH,
            ));
        }

        return $this;
    }

    public function fromAttribute(?Request $request = null): self
    {
        if ($request?->params) {
            foreach ($request->params as $name => $description) {
                $this->add(new Parameter(
                    name: $name,
                    value: '',
                    description: $description,
                    type: ParameterType::QUERY
                ));
            }
        }

        return $this;
    }
}
