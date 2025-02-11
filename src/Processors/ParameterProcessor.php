<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Illuminate\Routing\Route;
use Ninja\Cartographer\Attributes\Request as RequestAttribute;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Enums\Method;

final readonly class ParameterProcessor
{
    public function __construct(
        private Repository $config,
        private FormDataProcessor $formDataProcessor
    ) {}

    public function processParameters(
        Route $route,
        ?RequestAttribute $request = null,
        mixed $reflectionMethod = null
    ): ParameterCollection {
        $parameters = new ParameterCollection();
        $method = Method::tryFrom(mb_strtoupper($route->methods()[0]));

        // Process URL parameters
        $parameters->fromRoute($route);

        // Process attribute parameters
        if ($request) {
            $parameters->fromAttribute($request);
        }

        // Process form request parameters if enabled and it's NOT a GET/HEAD request
        if ($this->config->get('cartographer.enable_formdata') &&
            !in_array($method, [Method::GET, Method::HEAD])) {
            $formParameters = $this->formDataProcessor->process(
                $reflectionMethod,
                $this->config->get('cartographer.formdata', [])
            );
            $parameters = $parameters->merge($formParameters);
        }

        return $parameters;
    }
}
