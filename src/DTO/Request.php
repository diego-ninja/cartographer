<?php

namespace Ninja\Cartographer\DTO;

use Ninja\Cartographer\Collections\HeaderCollection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Enums\Method;
use JsonSerializable;
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
            headers: HeaderCollection::from($data['headers']),
            parameters: ParameterCollection::from($data['parameters']),
            url: Url::from($data['url']),
            authentication: $data['authentication'] ?? null,
            body: isset($data['body']) ? Body::from($data['body']) : null,
            responses: $data['responses'] ?? null,
            group: $data['group'] ?? null,
        );
    }

    public function group(): string
    {
        if ($this->method === Method::HEAD) {
            return '';
        }

        return $this->group !== null ? $this->group : explode('/', mb_trim($this->uri, '/'))[0] ?? 'Default';
    }

    public function getNestedPath(): array
    {
        if ($this->group !== null) {
            return [$this->group];
        }

        $segments = array_filter(explode('/', mb_trim($this->uri, '/')));

        if (empty($segments)) {
            return [];
        }

        $filteredSegments = array_filter($segments, function($segment) {
            return !str_starts_with($segment, '{');
        });

        if (empty($filteredSegments)) {
            return [];
        }

        return array_values($filteredSegments);
    }

    public function generateDefaultBody(): self
    {
        if (in_array($this->method, [Method::GET, Method::HEAD])) {
            return $this;
        }

        if ($this->body !== null) {
            return $this;
        }

        return new self(
            id: $this->id,
            name: $this->name,
            method: $this->method,
            uri: $this->uri,
            description: $this->description,
            headers: $this->headers,
            parameters: $this->parameters,
            url: $this->url,
            authentication: $this->authentication,
            body: Body::fromParameters($this->parameters),
            group: $this->group,
        );
    }

    public function array(): array
    {
        $data = [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'method' => $this->method->value,
            'uri' => $this->uri,
            'description' => $this->description,
            'headers' => $this->headers,
            'parameters' => $this->parameters,
            'url' => $this->url->array(),
            'authentication' => $this->authentication,
            'body' => $this->body,
            'responses' => $this->responses,
            'group' => $this->group,
        ];

        return array_filter($data, fn($value) => $value !== null);
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
