<?php

namespace Ninja\Cartographer\Collections;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Ninja\Cartographer\Attributes\Request;
use Ninja\Cartographer\Contracts\Serializable;
use Ninja\Cartographer\DTO\Parameters\Parameter;
use Ninja\Cartographer\Enums\ParameterFormat;
use Ninja\Cartographer\Enums\ParameterLocation;

final class ParameterCollection extends ExportableCollection
{
    /**
     * @param array<Parameter>|string|ParameterCollection $items
     */
    public static function from(array|string|Serializable $items): ParameterCollection
    {
        if ($items instanceof self) {
            return $items;
        }

        if (is_string($items)) {
            return self::from(json_decode($items, true));
        }

        return new self(array_map(fn(array|Parameter $parameter) => Parameter::from($parameter), $items));
    }

    public function forPostman(): array
    {
        return $this->map->forPostman()->values()->all();
    }

    public function forInsomnia(): array
    {
        return $this->map->forInsomnia()->values()->all();
    }

    public function byLocation(ParameterLocation $location): ParameterCollection
    {
        return $this->filter(fn(Parameter $parameter) => $parameter->location === $location);
    }

    public function byFormat(ParameterFormat $format): ParameterCollection
    {
        return $this->filter(fn(Parameter $parameter) => $parameter->format === $format);
    }

    public function getRequired(): self
    {
        return new self(
            $this->filter(fn(Parameter $parameter) =>
            $parameter->required
            )
        );
    }

    public function getOptional(): self
    {
        return new self(
            $this->filter(fn(Parameter $parameter) =>
            !$parameter->required
            )
        );
    }

    public function fromAttribute(?Request $request = null): self
    {
        $this->merge($request?->parameters() ?? []);
        return $this;
    }

    /**
     * @param array<Parameter>|ParameterCollection $items
     */
    public function merge($items): self
    {
        $merged = clone $this;

        foreach ($items as $parameter) {
            $exists = $this->contains(function (Parameter $existing) use ($parameter) {
                return $existing->name === $parameter->name
                    && $existing->location === $parameter->location;
            });

            if (!$exists) {
                $merged->add($parameter);
            }
        }

        return $merged;
    }

    public function groupByLocation(): array
    {
        return $this->groupBy(fn(Parameter $parameter) =>
        $parameter->location->value
        )->all();
    }

    public function hasLocation(ParameterLocation $location): bool
    {
        return $this->contains(fn(Parameter $parameter) =>
            $parameter->location === $location
        );
    }

    public function buildDescription(): string
    {
        $descriptions = [];

        foreach (ParameterLocation::cases() as $location) {
            $params = $this->byLocation($location);
            if ($params->isEmpty()) {
                continue;
            }

            $descriptions[] = sprintf(
                "\n### %s Parameters\n",
                ucfirst($location->value)
            );

            foreach ($params as $param) {
                $descriptions[] = sprintf(
                    "- `%s`: %s%s",
                    $param->name,
                    $param->description,
                    $param->required ? ' (Required)' : ''
                );
            }
        }

        return implode("\n", $descriptions);
    }

    public function find(string $name, ParameterLocation $location): ?Parameter
    {
        return $this->first(function (Parameter $parameter) use ($name, $location) {
            return $parameter->name === $name && $parameter->location === $location;
        });
    }
}
