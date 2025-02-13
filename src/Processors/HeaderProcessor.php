<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Illuminate\Routing\Route;
use Ninja\Cartographer\Collections\HeaderCollection;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;

final readonly class HeaderProcessor
{
    public function __construct(
        private Repository $config,
        private AttributeProcessor $attributeProcessor,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function processHeaders(Route $route): HeaderCollection
    {
        $configHeaders = $this->config->get('cartographer.headers', []);
        $request = $this->attributeProcessor->getRequestAttribute(RouteReflector::method($route));
        $collection = $this->attributeProcessor->getCollectionAttribute(RouteReflector::class($route));

        $headers = collect($configHeaders)
            ->merge($request?->headers ?? [])
            ->merge($collection?->headers ?? [])
            ->map(function ($header, $key) {
                if (is_string($key)) {
                    return ['key' => $key, 'value' => $header];
                }
                return $header;
            })
            ->values()
            ->all();

        return HeaderCollection::from($headers);
    }
}
