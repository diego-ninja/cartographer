<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Ninja\Cartographer\Collections\RequestCollection;
use Ninja\Cartographer\Collections\RequestGroupCollection;
use Ninja\Cartographer\DTO\Request;
use Ninja\Cartographer\DTO\Url;
use Ninja\Cartographer\Enums\Method;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;
use ReflectionMethod;

final readonly class RouteProcessor
{
    public function __construct(
        private Router                  $router,
        private Repository              $config,
        private AttributeProcessor      $attributeProcessor,
        private AuthenticationProcessor $authProcessor,
        private ParameterProcessor      $parameterProcessor,
        private BodyProcessor           $bodyProcessor,
        private HeaderProcessor         $headerProcessor,
        private ScriptsProcessor        $scriptsProcessor,
        private GroupProcessor          $groupProcessor,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function process(): RequestGroupCollection
    {
        $requests = collect($this->router->getRoutes())
            ->reduce(function (RequestCollection $collection, Route $route) {
                $this->processRoute($route, $collection);
                return $collection;
            }, new RequestCollection());

        return $this->groupProcessor->processRequests($requests);
    }

    /**
     * @throws ReflectionException
     */
    private function processRoute(Route $route, RequestCollection $collection): void
    {
        $methods = array_filter(
            array_map(fn($value) => Method::tryFrom(mb_strtoupper($value)), $route->methods()),
            fn(Method $method) => Method::HEAD !== $method,
        );

        $middlewares = $route->gatherMiddleware();
        if ( ! $this->shouldProcessRoute($middlewares)) {
            return;
        }

        // Get reflection method/function and attributes
        $reflector = RouteReflector::method($route);
        $requestAttributes = $reflector ? $this->attributeProcessor->getRequestAttribute($reflector) : null;

        // Get collection attributes if we have a class method
        $collectionAttributes = null;
        if ($reflector instanceof ReflectionMethod) {
            $collectionAttributes = $this->attributeProcessor->getCollectionAttribute(
                $reflector->getDeclaringClass()->getName(),
            );
        }

        foreach ($methods as $method) {
            $parameters = $this->parameterProcessor->processParameters($route);

            $request = new Request(
                id: Str::uuid(),
                name: $requestAttributes?->name ?? $route->getName() ?: $route->uri(),
                method: $method,
                uri: $route->uri(),
                description: $requestAttributes?->description ?? $this->getDescription($route),
                headers: $this->headerProcessor->processHeaders($route),
                parameters: $parameters,
                url: Url::fromRoute(
                    route: $route,
                    method: $method,
                    formParameters: $parameters,
                ),
                authentication: $this->authProcessor->processRouteAuthentication($middlewares),
                body: $this->bodyProcessor->processBody($route),
                scripts: $this->scriptsProcessor->processScripts($route),
                group: $requestAttributes?->group ?? $collectionAttributes?->group ?? null,
            );

            $collection->add($request);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function getDescription(Route $route): string
    {
        if ( ! $this->config->get('cartographer.include_doc_comments')) {
            return '';
        }

        $reflectionMethod = RouteReflector::method($route);
        return $reflectionMethod ? (new DocBlockProcessor())($reflectionMethod) : '';
    }

    private function shouldProcessRoute(array $middlewares): bool
    {
        return collect($middlewares)
            ->intersect($this->config->get('cartographer.include_middleware'))
            ->isNotEmpty();
    }
}
