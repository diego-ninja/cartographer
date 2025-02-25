<?php

namespace Ninja\Cartographer\DTO;

use JsonSerializable;
use Ninja\Cartographer\Collections\HeaderCollection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\Contracts\Exportable;
use Ninja\Cartographer\Enums\EventType;
use Ninja\Cartographer\Enums\Method;
use Ninja\Cartographer\Enums\ParameterLocation;
use Ninja\Cartographer\Processors\ParameterProcessor;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class Request implements JsonSerializable, Exportable
{
    public function __construct(
        public UuidInterface $id,
        public string $name,
        public Method $method,
        public string $uri,
        public string $description,
        public ParameterCollection $parameters,
        public Url $url,
        public ?Body $body = null,
        public ?array $authentication,
        public ScriptCollection $scripts,
        public ?string $group = null,
        public ?object $action = null,
    ) {}

    public static function from(string|array|self $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_string($data)) {
            return self::from(json_decode($data, true));
        }

        return new self(
            id: isset($data['id']) ? Uuid::fromString($data['id']) : Uuid::uuid4(),
            name: $data['name'],
            method: Method::from($data['method']),
            uri: $data['uri'],
            description: $data['description'] ?? '',
            parameters: ParameterCollection::from($data['parameters'] ?? []),
            url: isset($data['url']) ? Url::from($data['url']) : new Url('', [], []),
            authentication: $data['authentication'] ?? null,
            scripts: isset($data['scripts']) ? ScriptCollection::from($data['scripts']) : null,
            group: $data['group'] ?? null
        );
    }

    public function forPostman(): array
    {
        $pathParameters = $this->parameters->byLocation(ParameterLocation::Path);
        $queryParameters = $this->parameters->byLocation(ParameterLocation::Query);
        $headerParameters = $this->parameters->byLocation(ParameterLocation::Header);

        $request = [
            'name' => $this->name,
            'request' => array_filter([
                'method' => $this->method->value,
                'description' => $this->description,
                'url' => $this->buildPostmanUrl($pathParameters, $queryParameters),
                'header' => $headerParameters->forPostman(),
                'body' => $this->body?->forPostman(),
                'auth' => $this->authentication
            ])
        ];

        if ($this->scripts && !$this->scripts->isEmpty()) {
            $request['event'] = $this->scripts->forPostman();
        }

        return $request;
    }

    public function forInsomnia(): array
    {
        $pathParameters = $this->parameters->byLocation(ParameterLocation::Path);
        $queryParameters = $this->parameters->byLocation(ParameterLocation::Query);
        $headerParameters = $this->parameters->byLocation(ParameterLocation::Header);

        $request = [
            '_type' => 'request',
            'name' => $this->name,
            'description' => $this->description,
            'method' => $this->method->value,
            'url' => $this->buildInsomniaUrl($pathParameters, $queryParameters),
            'headers' => $headerParameters->forInsomnia(),
            'authentication' => $this->authentication
        ];

        if ($this->body) {
            $request = array_merge($request, $this->body->forInsomnia());
        }

        if ($this->scripts && !$this->scripts->isEmpty()) {
            if ($preRequest = $this->scripts->findByType(EventType::PreRequest)) {
                $request['preRequestScript'] = $preRequest->forInsomnia();
            }
            if ($postResponse = $this->scripts->findByType(EventType::AfterResponse)) {
                $request['postResponseScript'] = $postResponse->forInsomnia();
            }
        }

        return array_filter($request);
    }

    private function buildPostmanUrl(
        ParameterCollection $pathParams,
        ParameterCollection $queryParams
    ): array {
        $urlData = $this->url->array();

        if (!$pathParams->isEmpty()) {
            $urlData['variable'] = $pathParams->forPostman();
        }

        if (!$queryParams->isEmpty()) {
            $urlData['query'] = $queryParams->forPostman();
        }

        return $urlData;
    }

    private function buildInsomniaUrl(
        ParameterCollection $pathParams,
        ParameterCollection $queryParams
    ): string {
        $url = $this->url->forInsomnia();

        if (!$queryParams->isEmpty()) {
            $queryString = http_build_query(
                $queryParams->reduce(
                    fn(array $carry, $param) => array_merge(
                        $carry,
                        [$param->name => $param->value]
                    ),
                    []
                )
            );
            $url .= '?' . $queryString;
        }

        return $url;
    }

    public function array(): array
    {
        return array_filter([
            'id' => $this->id->toString(),
            'name' => $this->name,
            'method' => $this->method->value,
            'uri' => $this->uri,
            'description' => $this->description,
            'parameters' => $this->parameters->toArray(),
            'url' => $this->url->array(),
            'body' => $this->body?->jsonSerialize(),
            'authentication' => $this->authentication,
            'scripts' => $this->scripts?->toArray(),
            'group' => $this->group,
        ]);
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }

    public function getPath(): string
    {
        return trim($this->uri, '/');
    }

    public function getNestedPath(): array
    {
        if ($this->group !== null) {
            return [$this->group];
        }

        $segments = array_filter(explode('/', trim($this->uri, '/')));
        if (empty($segments)) {
            return [];
        }

        return array_values(
            array_filter(
                $segments,
                fn($segment) => !str_starts_with($segment, '{')
            )
        );
    }}
