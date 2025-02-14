<?php

namespace Ninja\Cartographer\DTO;

use JsonSerializable;
use Ninja\Cartographer\Collections\HeaderCollection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\Contracts\Exportable;
use Ninja\Cartographer\Enums\EventType;
use Ninja\Cartographer\Enums\Method;
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
        public ParameterProcessor $parameters,
        public Url $url,
        public ?array $authentication,
        public ScriptCollection $scripts,
        public ?string $group = null,
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
            parameters: ParameterProcessor::from($data['parameters'] ?? []),
            url: isset($data['url']) ? Url::from($data['url']) : new Url('', [], []),
            authentication: $data['authentication'] ?? null,
            scripts: isset($data['scripts']) ? ScriptCollection::from($data['scripts']) : null,
            group: $data['group'] ?? null
        );
    }

    public function forPostman(): array
    {
        $request = [
            'name' => $this->name,
            'request' => array_filter([
                'method' => $this->method->value,
                'description' => $this->description,
                'url' => $this->url->array(),
                'auth' => $this->authentication,
                ...$this->parameters->forPostman()
            ])
        ];

        if ($this->scripts && !$this->scripts->isEmpty()) {
            $request['event'] = $this->scripts->forPostman();
        }

        if ($this->authentication) {
            $request['request']['auth'] = $this->authentication;
        }

        return $request;
    }

    public function forInsomnia(): array
    {
        $base = [
            '_type' => 'request',
            'name' => $this->name,
            'description' => $this->description,
            'method' => $this->method->value,
            'url' => $this->url->forInsomnia(),
        ];

        $parameters = $this->parameters->forInsomnia();

        if ($this->authentication) {
            $base['authentication'] = $this->authentication;
        }

        if ($this->scripts && !$this->scripts->isEmpty()) {
            if ($preRequest = $this->scripts->findByType(EventType::PreRequest)) {
                $base['preRequestScript'] = $preRequest->forInsomnia();
            }
            if ($afterResponse = $this->scripts->findByType(EventType::AfterResponse)) {
                $base['afterResponseScript'] = $afterResponse->forInsomnia();
            }
        }

        return array_filter(array_merge($base, $parameters));
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
        return array_filter([
            'id' => $this->id->toString(),
            'name' => $this->name,
            'method' => $this->method->value,
            'uri' => $this->uri,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'url' => $this->url,
            'authentication' => $this->authentication,
            'scripts' => $this->scripts?->toArray(),
            'group' => $this->group,
        ]);
    }

    public function json(): string
    {
        return json_encode($this->array());
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }

    public function hasBodyContent(): bool
    {
        return in_array($this->method, [
            Method::POST,
            Method::PUT,
            Method::PATCH,
            Method::DELETE
        ]);
    }

    public function requiresAuthentication(): bool
    {
        return $this->authentication !== null;
    }

    public function hasScripts(): bool
    {
        return $this->scripts !== null && !$this->scripts->isEmpty();
    }
}
