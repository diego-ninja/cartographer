<?php

namespace Ninja\Cartographer\DTO;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use JsonSerializable;
use Ninja\Cartographer\Enums\Method;
use Ninja\Cartographer\Enums\ParameterLocation;
use Ninja\Cartographer\Processors\ParameterProcessor;

final readonly class Url implements JsonSerializable
{
    public function __construct(
        public string $raw,
        public array $host,
        public array $path,
        public array $variable = [],
        public array $query = [],
    ) {}

    public static function fromRoute(
        Route $route,
        Method $method,
        ParameterProcessor $parameters
    ): self {
        $uri = Str::of($route->uri())->replaceMatches('/{([[:alnum:]_]+)}/', ':$1');

        $data = [
            'raw' => '{{base_url}}/' . $uri,
            'host' => ['{{base_url}}'],
            'path' => explode('/', mb_trim($uri, '/')),
        ];

        $pathParameters = $parameters->getParametersByLocation(ParameterLocation::Path)
            ->map(fn($param) => [
                'key' => $param->name,
                'value' => $param->value ?? '',
                'description' => $param->description
            ])
            ->values()
            ->all();

        $data['variable'] = $pathParameters;

        if ($method === Method::GET) {
            $queryParameters = $parameters->getParametersByLocation(ParameterLocation::Query)
                ->map(fn($param) => [
                    'key' => $param->name,
                    'value' => $param->value ?? '',
                    'description' => $param->description,
                    'disabled' => false
                ])
                ->values()
                ->all();

            $data['query'] = $queryParameters;
        }

        return self::from($data);
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
        );
    }

    public function array(): array
    {
        return [
            'raw' => $this->raw,
            'host' => $this->host,
            'path' => $this->path,
            'variable' => $this->variable,
            'query' => $this->query,
        ];
    }

    public function forInsomnia(): string
    {
        $url = preg_replace('#/+#', '/', mb_trim($this->raw, '/'));

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
