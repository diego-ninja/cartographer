<?php

namespace Ninja\Cartographer\Exporters;

use Ninja\Cartographer\DTO\Request;
use Ramsey\Uuid\Uuid;

final class PostmanExporter extends AbstractExporter
{
    protected function generateStructure(): array
    {
        $structure = [
            'variable' => $this->generateVariables(),
            'info' => $this->generateInfo(),
            'item' => $this->processRequests(),
            'event' => $this->generateEvents(),
            'auth' => $this->generateAuth(),
        ];

        return array_filter($structure, fn($value) => !is_null($value));
    }

    protected function generateInfo(): array
    {
        return [
            '_postman_id' => Uuid::uuid4()->toString(),
            'name' => $this->config->get('cartographer.name'),
            'description' => $this->config->get('app.description'),
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            'version' => [
                'major' => 1,
                'minor' => 0,
                'patch' => 0,
            ],
        ];
    }

    protected function generateVariables(): array
    {
        $variables = [
            [
                'key' => 'base_url',
                'value' => $this->config->get('cartographer.base_url'),
                'type' => 'string',
                'enabled' => true,
            ],
        ];

        if ($this->authentication) {
            $variables[] = [
                'key' => 'token',
                'value' => $this->authentication->getToken(),
                'type' => 'string',
                'enabled' => true,
            ];
        }

        return $variables;
    }

    protected function generateAuth(): ?array
    {
        if (!$this->authentication) {
            return null;
        }

        return [
            'type' => $this->authentication->getType(),
            $this->authentication->getType() => [
                [
                    'key' => 'token',
                    'value' => '{{token}}',
                    'type' => 'string',
                ],
            ],
        ];
    }

    protected function generateEvents(): array
    {
        $events = [];

        $scripts = [
            'prerequest' => $this->getScript('pre-request'),
            'test' => $this->getScript('test'),
        ];

        foreach ($scripts as $type => $script) {
            if ($script) {
                $events[] = [
                    'listen' => $type,
                    'script' => [
                        'type' => 'text/javascript',
                        'exec' => explode("\n", $script),
                    ],
                ];
            }
        }

        return $events;
    }


    protected function processRequests(): array
    {
        return $this->config->get('cartographer.structured')
            ? $this->processStructuredRequests()
            : $this->processFlatRequests();
    }
    protected function processFlatRequests(): array
    {
        return $this->requests
            ->map(fn($request) => $this->createRequestItem($request))
            ->values()
            ->all();
    }

    protected function processStructuredRequests(): array
    {
        return $this->processNestedGroups($this->requests->groupByNestedPath());
    }

    protected function processNestedGroups(array $groups): array
    {
        $items = [];

        foreach ($groups as $segment => $data) {
            $folder = [
                '_postman_id' => Uuid::uuid4()->toString(),
                'name' => $segment,
                'description' => sprintf('Endpoints for %s', $segment),
                'item' => [],
                'protocolProfileBehavior' => $this->getProtocolProfileBehavior(),
            ];

            // Add requests to current folder
            foreach ($data['requests'] as $request) {
                $folder['item'][] = $this->createRequestItem($request);
            }

            // Process nested folders recursively
            if (!empty($data['children'])) {
                $folder['item'] = array_merge(
                    $folder['item'],
                    $this->processNestedGroups($data['children'])
                );
            }

            $items[] = $folder;
        }

        return $items;
    }

    protected function createRequestItem(Request $request): array
    {
        $item = [
            '_postman_id' => $request->id->toString(),
            'name' => $request->name,
            'request' => [
                'method' => $request->method->value,
                'header' => $request->headers->formatted(),
                'url' => $this->formatUrl($request),
                'description' => $request->description,
                'auth' => $this->getRequestAuth($request),
                'body' => $this->formatBody($request),
            ],
            'response' => [],
            'protocolProfileBehavior' => $this->getProtocolProfileBehavior(),
        ];

        // Add example responses if available
        if ($request->responses) {
            $item['response'] = array_map(
                fn($response) => $this->formatResponse($response),
                $request->responses
            );
        }

        return $item;
    }

    protected function formatUrl(Request $request): array
    {
        $url = $request->url->array();

        // Add protocol if missing
        if (!isset($url['protocol'])) {
            $url['protocol'] = parse_url($this->config->get('cartographer.base_url'), PHP_URL_SCHEME) ?? 'http';
        }

        return $url;
    }

    protected function formatBody(Request $request): ?array
    {
        $request = $request->generateDefaultBody();
        return $request->body?->forPostman();
    }

    protected function formatResponse(array $response): array
    {
        return [
            'name' => $response['name'] ?? 'Example Response',
            'originalRequest' => $response['request'] ?? null,
            'status' => $response['status'] ?? 'OK',
            'code' => $response['code'] ?? 200,
            '_postman_previewlanguage' => 'json',
            'header' => $response['headers'] ?? [],
            'cookie' => [],
            'body' => $response['body'] ?? ''
        ];
    }
    protected function getRequestAuth(Request $request): ?array
    {
        // Allow per-request auth override
        if ($request->authentication) {
            return [
                'type' => $request->authentication['type'],
                $request->authentication['type'] => [
                    [
                        'key' => 'token',
                        'value' => $request->authentication['token'],
                        'type' => 'string'
                    ]
                ]
            ];
        }

        return null;
    }
    protected function getProtocolProfileBehavior(): array
    {
        return array_filter([
            'disableBodyPruning' => $this->config->get('cartographer.protocol_profile_behavior.disable_body_pruning', false),
            'followRedirects' => true,
            'strictSSL' => true,
        ]);
    }
}
