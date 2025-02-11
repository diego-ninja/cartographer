<?php

namespace Ninja\Cartographer\Processors;
use Ninja\Cartographer\Attributes\Request as RequestAttribute;
use Ninja\Cartographer\Collections\HeaderCollection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Collections\RequestCollection;
use Ninja\Cartographer\Concerns\HasAuthentication;
use Ninja\Cartographer\DTO\Body;
use Ninja\Cartographer\DTO\Request;
use Ninja\Cartographer\DTO\Url;
use Ninja\Cartographer\Enums\BodyMode;
use Ninja\Cartographer\Enums\Method;
use Closure;
use Illuminate\Config\Repository;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

final class RouteProcessor
{
    use HasAuthentication;

    public function __construct(
        private readonly Router $router,
        private readonly Repository $config,
        private readonly AttributeProcessor $attributeProcessor,
        private readonly AuthenticationProcessor $authProcessor,
        private readonly ParameterProcessor $parameterProcessor,
        private readonly BodyProcessor $bodyProcessor,
        private readonly HeaderProcessor $headerProcessor
    ) {}

    /**
     * @throws ReflectionException
     */
    public function process(): RequestCollection
    {
        return collect($this->router->getRoutes())
            ->reduce(function (RequestCollection $collection, Route $route) {
                $this->processRoute($route, $collection);
                return $collection;
            }, new RequestCollection());
    }

    /**
     * @throws ReflectionException
     */
    private function processRoute(Route $route, RequestCollection $collection): void
    {
        $methods = array_filter(
            array_map(fn($value) => Method::tryFrom(mb_strtoupper($value)), $route->methods()),
            fn(Method $method) => Method::HEAD !== $method
        );

        $middlewares = $route->gatherMiddleware();
        if (!$this->shouldProcessRoute($middlewares)) {
            return;
        }

        // Get reflection method/function and attributes
        $reflector = $this->getReflectionMethod($route->getAction());
        $requestAttributes = $reflector ? $this->attributeProcessor->getRequestAttribute($reflector) : null;

        // Get collection attributes if we have a class method
        $collectionAttributes = null;
        if ($reflector instanceof ReflectionMethod) {
            $collectionAttributes = $this->attributeProcessor->getCollectionAttribute(
                $reflector->getDeclaringClass()->getName()
            );
        }

        foreach ($methods as $method) {
            $parameters = $this->parameterProcessor->processParameters($route, $requestAttributes, $reflector);

            $request = new Request(
                id: Str::uuid(),
                name: $requestAttributes?->name ?? $route->getName() ?: $route->uri(),
                method: $method,
                uri: $route->uri(),
                description: $requestAttributes?->description ?? $this->getDescription($route),
                headers: $this->headerProcessor->processHeaders($requestAttributes),
                parameters: $parameters,
                url: Url::fromRoute(
                    route: $route,
                    method: $method,
                    formParameters: $parameters
                ),
                authentication: $this->authProcessor->processRouteAuthentication($middlewares),
                body: $this->bodyProcessor->processBody($route, $reflector),
                group: $requestAttributes?->group ?? $collectionAttributes?->group ?? null
            );

            $collection->add($request);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function getDescription(Route $route): string
    {
        if (!$this->config->get('cartographer.include_doc_comments')) {
            return '';
        }

        $reflectionMethod = $this->getReflectionMethod($route->getAction());
        return $reflectionMethod ? (new DocBlockProcessor())($reflectionMethod) : '';
    }

    private function shouldProcessRoute(array $middlewares): bool
    {
        return collect($middlewares)
            ->intersect($this->config->get('cartographer.include_middleware'))
            ->isNotEmpty();
    }

    /**
     * @throws ReflectionException
     */
    private function getReflectionMethod(array $action): ReflectionMethod|ReflectionFunction|null
    {
        if ($this->containsSerializedClosure($action)) {
            $action['uses'] = unserialize($action['uses'])->getClosure();
        }

        if ($action['uses'] instanceof Closure) {
            return new ReflectionFunction($action['uses']);
        }

        if (!is_string($action['uses'])) {
            return null;
        }

        $routeData = explode('@', $action['uses']);
        if (2 !== count($routeData)) {
            return null;
        }

        $reflection = new ReflectionClass($routeData[0]);
        if (!$reflection->hasMethod($routeData[1])) {
            return null;
        }

        return $reflection->getMethod($routeData[1]);
    }

    private function containsSerializedClosure(array $action): bool
    {
        if (!is_string($action['uses'])) {
            return false;
        }

        $needles = [
            'C:32:"Opis\\Closure\\SerializableClosure',
            'O:47:"Laravel\\SerializableClosure\\SerializableClosure',
            'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure',
        ];

        return Str::startsWith($action['uses'], $needles);
    }
}
