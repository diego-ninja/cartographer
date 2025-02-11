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
    ) {
        $this->resolveAuth();
    }

    /**
     * @throws ReflectionException
     */
    public function process(): RequestCollection
    {
        $routes = collect($this->router->getRoutes());
        $collection = new RequestCollection();

        foreach ($routes as $route) {
            $this->processRoute($route, $collection);
        }

        return $collection;
    }

    /**
     * @throws ReflectionException
     */
    protected function processRoute(Route $route, RequestCollection $collection): void
    {
        $methods = array_filter(
            array_map(fn($value) => Method::tryFrom(mb_strtoupper($value)), $route->methods()),
            fn(Method $method) => Method::HEAD !== $method,
        );

        $middlewares = $route->gatherMiddleware();

        // Get reflection method/function and attributes if available
        $reflector = $this->getReflectionMethod($route->getAction());
        $requestAttributes = $reflector ? $this->attributeProcessor->getRequestAttribute($reflector) : null;

        // Get collection attributes only if we have a class method
        $collectionAttributes = null;
        if ($reflector instanceof ReflectionMethod) {
            $collectionAttributes = $this->attributeProcessor->getCollectionAttribute(
                $reflector->getDeclaringClass()->getName()
            );
        }

        foreach ($methods as $method) {
            if (!$this->shouldProcessRoute($middlewares)) {
                continue;
            }

            $request = new Request(
                id: Str::uuid(),
                name: $requestAttributes?->name ?? $route->getName() ?: $route->uri(),
                method: $method,
                uri: $route->uri(),
                description: $requestAttributes?->description ?? $this->getDescription($route),
                headers: $this->getHeaders($requestAttributes),
                parameters: $this->getParameters($route),
                url: Url::fromRoute(
                    route: $route,
                    method: $method,
                    formParameters: $this->getParameters($route),
                ),
                authentication: $this->getAuthenticationInfo($middlewares),
                body: Method::GET === $method ? null : $this->getBody($route),
                group: $requestAttributes?->group ?? $collectionAttributes?->group ?? null,
            );

            $collection->add($request);
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function getBody(Route $route): ?Body
    {
        if (in_array($route->methods()[0], ['GET', 'HEAD'])) {
            return null;
        }

        $reflectionMethod = $this->getReflectionMethod($route->getAction());
        if (!$reflectionMethod || !$this->config->get('cartographer.enable_formdata')) {
            return null;
        }

        $formParameters = (new FormDataProcessor())->process($reflectionMethod);
        if ($formParameters->isEmpty()) {
            return null;
        }

        return Body::fromParameters(
            parameters: $formParameters,
            formdata: $this->config->get('cartographer.formdata', []),
            mode: BodyMode::from($this->config->get('cartographer.body_mode', BodyMode::Raw->value)),
        );

    }

    /**
     * @throws ReflectionException
     */
    protected function getDescription(Route $route): string
    {
        if (!$this->config->get('cartographer.include_doc_comments')) {
            return '';
        }

        $reflectionMethod = $this->getReflectionMethod($route->getAction());
        if (!$reflectionMethod) {
            return '';
        }

        return (new DocBlockProcessor())($reflectionMethod);
    }

    protected function shouldProcessRoute(array $middlewares): bool
    {
        foreach ($middlewares as $middleware) {
            if (in_array($middleware, $this->config->get('cartographer.include_middleware'))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws ReflectionException
     */
    protected function getParameters(Route $route, ?RequestAttribute $request = null): ParameterCollection
    {
        $parameters = new ParameterCollection();
        return $parameters
            ->fromRoute($route)
            ->fromAttribute($request)
            ->when(
                $this->config->get('cartographer.enable_formdata') && Method::GET->value === $route->methods()[0],
                fn(ParameterCollection $collection) => $collection->fromFormRequest(
                    $this->getReflectionMethod($route->getAction()),
                    $this->config->get('cartographer.formdata', [])
                )
            );
    }

    protected function getAuthenticationInfo(array $middlewares): ?array
    {
        if (in_array($this->config->get('cartographer.auth_middleware'), $middlewares)) {
            $config = $this->config->get('cartographer.authentication');
            return [
                'type' => $config['method'],
                'token' => $config['token'] ?? '{{token}}',
            ];
        }
        return null;
    }


    /**
     * @throws ReflectionException
     */
    protected function getReflectionMethod(array $action): ReflectionMethod|ReflectionFunction|null
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
        if ( ! is_string($action['uses'])) {
            return false;
        }

        $needles = [
            'C:32:"Opis\\Closure\\SerializableClosure',
            'O:47:"Laravel\\SerializableClosure\\SerializableClosure',
            'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure',
        ];

        foreach ($needles as $needle) {
            if (str_starts_with($action['uses'], $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function getHeaders(?RequestAttribute $request = null): HeaderCollection
    {
        $configHeaders = $this->config->get('cartographer.headers', []);

        if (empty($request?->headers)) {
            return HeaderCollection::from($configHeaders);
        }

        $mergedHeaders = collect($configHeaders)
            ->merge($request->headers)
            ->map(function($header, $key) {
                if (is_string($key)) {
                    return ['key' => $key, 'value' => $header];
                }
                return $header;
            })
            ->values()
            ->all();

        return HeaderCollection::from($mergedHeaders);
    }
}
