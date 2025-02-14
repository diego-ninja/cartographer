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
                $method = RouteReflector::method($this->route);
                if ($method) {
                    $this->processCollectionHeaders();
                    $this->processRequestHeaders($method);
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
    private function processCollectionHeaders(): void
    {
        $collectionClass = RouteReflector::class($this->route);
        $collectionAttributes = $this->attributeProcessor->getCollectionAttribute($collectionClass);

        if ($collectionAttributes?->headers) {
            foreach ($collectionAttributes->headers as $key => $header) {
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
