<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Illuminate\Routing\Route;
use Ninja\Cartographer\DTO\Body;
use Ninja\Cartographer\Enums\BodyMode;
use Ninja\Cartographer\Enums\Method;
use Ninja\Cartographer\Services\BodyContentHandler;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;

final readonly class BodyProcessor
{
    public function __construct(
        private Repository $config,
        private FormDataProcessor $formDataProcessor,
        private BodyContentHandler $bodyContentHandler,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function processBody(Route $route): ?Body
    {
        $method = Method::tryFrom(mb_strtoupper($route->methods()[0]));

        if (in_array($method, [Method::GET, Method::HEAD])) {
            return null;
        }

        if (!RouteReflector::action($route) || !$this->config->get('cartographer.enable_formdata')) {
            return null;
        }

        $formParameters = $this->formDataProcessor->process($route, $method);
        if ($formParameters->isEmpty()) {
            return null;
        }

        $mode = BodyMode::from($this->config->get('cartographer.body_mode', BodyMode::Raw->value));
        $formdata = $this->config->get('cartographer.formdata', []);

        $content = $this->bodyContentHandler->prepareContent(
            parameters: $formParameters,
            mode: $mode,
            formdata: $formdata
        );

        return new Body(
            mode: $mode,
            content: $content,
            options: $this->bodyContentHandler->getBodyOptions($mode),
        );
    }
}
