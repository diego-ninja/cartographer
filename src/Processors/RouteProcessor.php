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
use Ninja\Cartographer\Services\ParameterService;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;

final readonly class RouteProcessor
{
    public function __construct(
        private Router $router,
        private Repository $config,
        private AttributeProcessor $attributeProcessor,
        private AuthenticationProcessor $authProcessor,
        private BodyProcessor $bodyProcessor,
        private ScriptsProcessor $scriptsProcessor,
        private GroupProcessor $groupProcessor,
        private ParameterService $parameterService,
    ) {}

    public function process(): RequestGroupCollection
    {
        $requests = collect($this->router->getRoutes())
            ->reduce(function (RequestCollection $collection, Route $route) {
                $this->processRoute($route, $collection);
                return $collection;
            }, new RequestCollection());

        return $this->groupProcessor->processRequests($requests);
    }

    private function processRoute(Route $route, RequestCollection $collection): void
    {
        if (!$this->shouldProcessRoute($route)) {
            return;
        }

        try {
            $action = RouteReflector::action($route);
            $controller = RouteReflector::controller($route);
            if (!$action) {
                return;
            }

            $requestAttributes = $this->attributeProcessor->getRequestAttribute($action);
            $groupAttributes = $controller ? $this->attributeProcessor->getGroupAttribute($controller) : null;

            foreach ($this->getValidMethods($route) as $method) {
                $parameters = $this->parameterService->processRouteParameters($route);

                $request = new Request(
                    id: Str::uuid(),
                    name: $this->resolveRequestName($requestAttributes, $route),
                    method: $method,
                    uri: $route->uri(),
                    description: $this->resolveDescription($requestAttributes, $route),
                    parameters: $parameters,
                    url: Url::fromRoute($route, $method, $parameters),
                    body: $this->bodyProcessor->processBody($route),
                    authentication: $this->resolveAuthentication($route),
                    scripts: $this->scriptsProcessor->processScripts($route),
                    group: $requestAttributes?->group ?? $groupAttributes?->group ?? null,
                    action: $action
                );

                $collection->add($request);
            }
        } catch (ReflectionException) {
            return;
        }
    }

    private function shouldProcessRoute(Route $route): bool
    {
        $middlewares = $route->gatherMiddleware();
        return collect($middlewares)
            ->intersect($this->config->get('cartographer.include_middleware'))
            ->isNotEmpty();
    }

    private function getValidMethods(Route $route): array
    {
        return array_filter(
            array_map(
                fn($method) => Method::tryFrom(strtoupper($method)),
                $route->methods()
            ),
            fn(?Method $method) => $method !== null && $method !== Method::HEAD
        );
    }

    private function resolveRequestName(?\Ninja\Cartographer\Attributes\Request $attributes, Route $route): string
    {
        return $attributes?->name ?? $route->getName() ?? $route->uri();
    }

    private function resolveDescription(?\Ninja\Cartographer\Attributes\Request $attributes, Route $route): string
    {
        if ($attributes?->description) {
            return $attributes->description;
        }

        if ($this->config->get('cartographer.include_doc_comments', false)) {
            try {
                $reflectionMethod = RouteReflector::action($route);
                return $reflectionMethod ? (new DocBlockProcessor())($reflectionMethod) : '';
            } catch (ReflectionException) {
                return '';
            }
        }

        return '';
    }

    private function resolveAuthentication(Route $route): ?array
    {
        $middlewares = $route->gatherMiddleware();
        return $this->authProcessor->processRouteAuthentication($middlewares);
    }
}
