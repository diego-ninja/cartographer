<?php

namespace Ninja\Cartographer\Mappers;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\DTO\Parameters\PathParameter;

final readonly class RouteParameterMapper extends ParameterMapper
{
    public function __construct(
        private Route $route
    ) {}

    public function map(): Collection
    {
        $parameters = [];
        $uri = $this->route->uri();

        preg_match_all('/\{([^}]+)}/', $uri, $matches);

        foreach ($matches[1] ?? [] as $param) {
            $isOptional = str_ends_with($param, '?');
            $name = rtrim($param, '?');

            $parameters[] = new PathParameter(
                name: $name,
                description: sprintf("URL parameter: %s", $name),
                required: !$isOptional
            );
        }

        return ParameterCollection::from($parameters);
    }
}
