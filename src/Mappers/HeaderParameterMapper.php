<?php

namespace Ninja\Cartographer\Mappers;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\DTO\Parameters\HeaderParameter;
use Ninja\Cartographer\Processors\AttributeProcessor;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;
use ReflectionMethod;

final readonly class HeaderParameterMapper extends ParameterMapper
{
    private ParameterCollection $parameters;

    public function __construct(
        private array $headers,
        private ?Route $route = null,
        private AttributeProcessor $attributeProcessor
    ) {
        $this->parameters = new ParameterCollection();
    }
    public function map(): Collection
    {
        foreach ($this->headers as $header) {
            $this->parameters->put($header["key"], new HeaderParameter(
                name: $header['key'],
                value: $header['value'],
                description: $header['description'] ?? ''
            ));
        }

        if ($this->route) {
            try {
                $action = RouteReflector::action($this->route);
                if ($action) {
                    $this->processGroupHeaders();
                    $this->processRequestHeaders($action);
                }
            } catch (ReflectionException $e) {
                // Do nothing
            }
        }

        return $this->parameters;
    }

    /**
     * @throws ReflectionException
     */
    private function processGroupHeaders(): void
    {
        $controller = RouteReflector::controller($this->route);
        $attribute = $controller ? $this->attributeProcessor->getGroupAttribute($controller) : null;

        if ($attribute?->headers) {
            foreach ($attribute->headers as $key => $header) {
                $this->parameters->put($key, new HeaderParameter(
                    name: $key,
                    value: $header,
                    description: ''
                ));
            }
        }
    }

    private function processRequestHeaders(ReflectionMethod $method): void
    {
        $requestAttributes = $this->attributeProcessor->getRequestAttribute($method);

        if ($requestAttributes?->headers) {
            foreach ($requestAttributes->headers as $key => $header) {
                $this->parameters->put($key, new HeaderParameter(
                    name: $key,
                    value: $header,
                    description: ''
                ));
            }
        }
    }
}
