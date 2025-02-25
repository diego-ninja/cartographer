<?php

namespace Ninja\Cartographer\DTO;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use JsonSerializable;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Enums\Method;
use Ninja\Cartographer\Enums\ParameterLocation;

final readonly class Url implements JsonSerializable
{
    public function __construct(
        public string $raw,
        public array $host,
        public array $path,
        public array $variable = [],
        public array $query = [],
        public ?string $protocol = null,
        public ?string $port = null
    ) {}

    public static function fromRoute(
        Route $route,
        Method $method,
        ParameterCollection $parameters
    ): self {
        $uri = Str::of($route->uri())->replaceMatches('/{([[:alnum:]_]+)\??}/', ':$1');

        $baseUrl = config('cartographer.base_url');
        $parsedUrl = parse_url($baseUrl);

        $raw = rtrim('{{base_url}}/' . $uri, '/');

        $host = isset($parsedUrl['host'])
            ? [$parsedUrl['host']]
            : ['{{base_url}}'];

        $path = array_values(
            array_filter(
                explode('/', trim($uri, '/')),
                fn($segment) => !empty($segment)
            )
        );

        $pathParameters = $parameters->byLocation(ParameterLocation::Path)
            ->map(fn($param) => [
                'key' => $param->name,
                'value' => $param->value ?? '',
                'description' => $param->description,
                'type' => 'string',
                'required' => true
            ])
            ->values()
            ->all();


        $queryParameters = [];
        if ($method === Method::GET) {
            $queryParameters = $parameters->byLocation(ParameterLocation::Query)
                ->map(fn($param) => [
                    'key' => $param->name,
                    'value' => $param->value ?? '',
                    'description' => $param->description,
                    'disabled' => false
                ])
                ->values()
                ->all();
        }

        return new self(
            raw: $raw,
            host: $host,
            path: $path,
            variable: $pathParameters,
            query: $queryParameters,
            protocol: $parsedUrl['scheme'] ?? 'http',
            port: isset($parsedUrl['port']) ? (string)$parsedUrl['port'] : null
        );
    }

    public static function from(string|array $data): self
    {
        if (is_string($data)) {
            return self::from(json_decode($data, true));
        }

        return new self(
            raw: $data['raw'],
            host: $data['host'],
            path: $data['path'],
            variable: $data['variable'] ?? [],
            query: $data['query'] ?? [],
            protocol: $data['protocol'] ?? 'http',
            port: $data['port'] ?? null
        );
    }

    public function array(): array
    {
        $result = [
            'raw' => $this->raw,
            'protocol' => $this->protocol,
            'host' => $this->host,
            'path' => $this->path,
        ];

        if (!empty($this->port)) {
            $result['port'] = $this->port;
        }

        if (!empty($this->variable)) {
            $result['variable'] = $this->variable;
        }

        if (!empty($this->query)) {
            $result['query'] = $this->query;
        }

        return $result;
    }

    public function forInsomnia(): string
    {
        $url = preg_replace('#/+#', '/', trim($this->raw, '/'));

        if (!empty($this->query)) {
            $queryString = http_build_query(
                array_combine(
                    array_column($this->query, 'key'),
                    array_column($this->query, 'value'),
                ),
            );
            $url .= '?' . $queryString;
        }

        return $url;
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
