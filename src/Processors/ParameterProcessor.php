<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Illuminate\Routing\Route;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Enums\Method;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;

final readonly class ParameterProcessor
{
    public function __construct(
        private Repository $config,
        private FormDataProcessor $formDataProcessor,
        private AttributeProcessor $attributeProcessor,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function processParameters(
        Route $route,
    ): ParameterCollection {
        $parameters = new ParameterCollection();
        $method = Method::tryFrom(mb_strtoupper($route->methods()[0]));
        $rfx = RouteReflector::method($route);

        // Process URL parameters
        $parameters->fromRoute($route);

        $attribute = $this->attributeProcessor->getRequestAttribute($rfx);
        if ($attribute) {
            $parameters->fromAttribute($attribute);
        }

        // Process form request parameters if enabled and it's NOT a GET/HEAD request
        if ($this->config->get('cartographer.enable_formdata') &&
            ! in_array($method, [Method::GET, Method::HEAD])) {
            $formParameters = $this->formDataProcessor->process(
                route: $route,
                formdata: $this->config->get('cartographer.formdata', []),
            );

            $parameters = $parameters->merge($formParameters);
        }

        return $parameters;
    }
}
