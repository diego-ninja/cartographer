<?php

namespace Ninja\Cartographer\DTO;

use JsonSerializable;
use Ninja\Cartographer\Collections\HeaderCollection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\Enums\EventType;
use Ninja\Cartographer\Enums\Method;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class Request implements JsonSerializable
{
    public function __construct(
        public UuidInterface $id,
        public string $name,
        public Method $method,
        public string $uri,
        public string $description,
        public HeaderCollection $headers,
        public ParameterCollection $parameters,
        public Url $url,
        public ?array $authentication,
        public ?Body $body,
        public ScriptCollection $scripts,
        public ?array $responses = null,
        public ?string $group = null,
    ) {}

    public static function from(string|array $data): Request
    {
        if (is_string($data)) {
            return self::from(json_decode($data, true));
        }

        return new self(
            id: Uuid::fromString($data['id']),
            name: $data['name'],
            method: Method::from($data['method']),
            uri: $data['uri'],
            description: $data['description'] ?? null,
            headers: HeaderCollection::from($data['headers'] ?? []),
            parameters: ParameterCollection::from($data['parameters'] ?? []),
            url: Url::from($data['url']),
            authentication: $data['authentication'] ?? null,
            body: isset($data['body']) ? Body::from($data['body']) : null,
            scripts: ScriptCollection::from($data['scripts'] ?? []),
            responses: $data['responses'] ?? null,
            group: $data['group'] ?? null,
        );
    }

    public function forPostman(): array
    {
        $request = [
            'name' => $this->name,
            'request' => [
                'method' => $this->method->value,
                'url' => $this->url->array(),
                'description' => $this->description,
            ],
        ];

        if ( ! $this->headers->isEmpty()) {
            $request['request']['header'] = $this->headers->formatted();
        }

        if ($this->authentication) {
            $request['request']['auth'] = $this->authentication;
        }

        if ($this->body) {
            $request['request']['body'] = $this->body->forPostman();
        }

        if ( ! $this->scripts->isEmpty()) {
            $request['event'] = $this->scripts->map(fn(Script $script) => $script->forPostman());
        }

        return $request;
    }

    public function forInsomnia(string $parentId): array
    {
        $request = [
            '_id' => 'req_' . $this->id->toString(),
            '_type' => 'request',
            'parentId' => $parentId,
            'name' => $this->name,
            'description' => $this->description,
            'method' => $this->method->value,
            'url' => sprintf('{{ base_url }}/%s', $this->url->forInsomnia()),
        ];

        if ( ! $this->headers->isEmpty()) {
            $request['headers'] = $this->headers->formatted();
        }

        if ($this->authentication) {
            $request['authentication'] = $this->authentication;
        }

        if ($this->body) {
            $request['body'] = $this->body->forInsomnia();
        }

        if ( ! $this->scripts->isEmpty()) {
            $request['preRequestScript'] = $this->scripts->findByType(EventType::PreRequest)->forInsomnia();
            $request['afterResponseScript'] = $this->scripts->findByType(EventType::AfterResponse)->forInsomnia();
        }

        return $request;
    }
    public function group(): string
    {
        if (Method::HEAD === $this->method) {
            return '';
        }

        return null !== $this->group ? $this->group : explode('/', mb_trim($this->uri, '/'))[0] ?? 'Default';
    }

    public function getNestedPath(): array
    {
        if (null !== $this->group) {
            return [$this->group];
        }

        $segments = array_filter(explode('/', mb_trim($this->uri, '/')));

        if (empty($segments)) {
            return [];
        }

        $filteredSegments = array_filter($segments, fn($segment) => ! str_starts_with($segment, '{'));

        if (empty($filteredSegments)) {
            return [];
        }

        return array_values($filteredSegments);
    }

    public function array(): array
    {
        $data = [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'method' => $this->method->value,
            'uri' => $this->uri,
            'description' => $this->description,
            'headers' => $this->headers->toArray(),
            'parameters' => $this->parameters->toArray(),
            'url' => $this->url->array(),
            'authentication' => $this->authentication,
            'body' => $this->body,
            'scripts' => $this->scripts->toArray(),
            'responses' => $this->responses,
            'group' => $this->group,
        ];

        return array_filter($data, fn($value) => null !== $value);
    }

    public function json(): string
    {
        return json_encode($this->array());
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }
}
