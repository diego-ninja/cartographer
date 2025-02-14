<?php

namespace Ninja\Cartographer\Collections;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Ninja\Cartographer\Attributes\Request;
use Ninja\Cartographer\DTO\Parameters\Parameter;
use Ninja\Cartographer\Enums\ParameterFormat;
use Ninja\Cartographer\Enums\ParameterLocation;

final class ParameterCollection extends Collection
{
    /**
     * @param array<Parameter> $parameters
     */
    public static function from(array $parameters): ParameterCollection
    {
        return new self(array_map(fn(array|Parameter $parameter) => Parameter::from($parameter), $parameters));
    }

    public function byLocation(ParameterLocation $type): ParameterCollection
    {
        return $this->filter(fn(Parameter $parameter) => $parameter->location === $type);
    }

    public function byFormat(ParameterFormat $format): ParameterCollection
    {
        return $this->filter(fn(Parameter $parameter) => $parameter->format === $format);
    }

    public function fromAttribute(?Request $request = null): self
    {
        $this->merge($request?->parameters() ?? []);
        return $this;
    }
}
