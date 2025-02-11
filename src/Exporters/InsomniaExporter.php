<?php

namespace Ninja\Cartographer\Exporters;

use Ninja\Cartographer\DTO\Parameter;
use Ninja\Cartographer\DTO\Request;
use Ninja\Cartographer\Enums\Method;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class InsomniaExporter extends AbstractExporter
{
    private string $workspaceId;
    private array $resourceIds = [];
    protected function generateStructure(): array
    {
        $this->workspaceId = 'wrk_' . Str::uuid()->toString();

        return [
            '_type' => 'export',
            '__export_format' => 4,
            '__export_date' => date('Y-m-d H:i:s'),
            '__export_source' => 'cartographer',
            'resources' => $this->generateResources(),
        ];
    }

    protected function generateResources(): array
    {
        return array_merge(
            [$this->createWorkspace(), $this->createEnvironment()],
            $this->processRequests()
        );
    }

    protected function createWorkspace(): array
    {
        return [
            '_id' => $this->workspaceId,
            '_type' => 'workspace',
            'parentId' => null,
            'name' => $this->config->get('cartographer.name'),
            'description' => $this->config->get('app.description'),
            'scope' => 'collection',
            'created' => now()->getTimestamp(),
            'modified' => now()->getTimestamp(),
        ];
    }

    protected function createEnvironment(): array
    {
        $data = [
            '_id' => 'env_' . Str::uuid()->toString(),
            '_type' => 'environment',
            'parentId' => $this->workspaceId,
            'name' => 'Base Environment',
            'data' => [
                'base_url' => $this->config->get('cartographer.base_url')
            ]
        ];

        if ($this->authentication) {
            $data['data']['token'] = $this->authentication->getToken();
        }

        return $data;
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
            ->filter(fn(Request $request) => Method::HEAD !== $request->method)
            ->map(fn(Request $request) => $this->createRequestResource($request, $this->workspaceId))
            ->values()
            ->all();
    }

    protected function processStructuredRequests(): array
    {
        $resources = [];
        $grouped = $this->requests
            ->filter(fn(Request $request) => Method::HEAD !== $request->method)
            ->groupByNestedPath();

        $this->processNestedGroups($grouped, $resources);

        return $resources;
    }

    protected function processNestedGroups(array $groups, array &$resources, ?string $parentId = null): void
    {
        $parentId = $parentId ?? $this->workspaceId;
        $sortKey = 0;

        foreach ($groups as $segment => $data) {
            $folderId = $this->validateResourceId('fld_' . Str::uuid()->toString(), 'fld');
            $this->resourceIds[] = $folderId;

            // Create folder
            $resources[] = [
                '_id' => $folderId,
                '_type' => 'request_group',
                'parentId' => $parentId,
                'name' => Str::title($segment),
                'description' => sprintf('Endpoints for %s', $segment),
                'environment' => [],
                'environmentPropertyOrder' => null,
                'metaSortKey' => $sortKey,
                'modified' => now()->getTimestamp(),
                'created' => now()->getTimestamp(),
            ];

            // Add requests to current folder
            foreach ($data['requests'] as $request) {
                $resources[] = $this->createRequestResource($request, $folderId, $sortKey);
                $sortKey += 100;
            }

            // Process nested folders
            if (!empty($data['children'])) {
                $this->processNestedGroups($data['children'], $resources, $folderId);
            }

            $sortKey += 1000;
        }
    }

    protected function createRequestResource(Request $request, string $parentId, int $sortKey = 0): array
    {
        $requestId = 'req_' . $request->id->toString();
        $this->resourceIds[] = $requestId;

        $requestResource = [
            '_id' => $requestId,
            '_type' => 'request',
            'parentId' => $parentId,
            'modified' => now()->getTimestamp(),
            'created' => now()->getTimestamp(),
            'url' => $this->formatUrl($request),
            'name' => $request->name,
            'description' => $request->description,
            'method' => $request->method->value,
            'headers' => $this->formatHeaders($request),
            'authentication' => $this->formatAuthentication(),
            'metaSortKey' => $sortKey,
            'isPrivate' => false,
            'settingStoreCookies' => true,
            'settingSendCookies' => true,
            'settingDisableRenderRequestBody' => false,
            'settingEncodeUrl' => true,
            'settingRebuildPath' => true,
            'settingFollowRedirects' => 'global',
        ];

        // Add body if present
        if ($request->body) {
            $requestResource['body'] = $this->formatBody($request);
        }

        // Add parameters if present
        if (!$request->parameters->isEmpty()) {
            $requestResource['parameters'] = $this->formatParameters($request);
        }

        return $requestResource;
    }

    protected function formatUrl(Request $request): string
    {
        return sprintf(
            '{{ base_url }}/%s',
            $this->cleanUrl($request->uri)
        );
    }

    protected function formatParameters(Request $request): array
    {
        return $request->parameters
            ->map(fn(Parameter $parameter) => [
                'name' => $parameter->name,
                'value' => $parameter->value,
                'description' => $parameter->description,
                'disabled' => $parameter->disabled,
                'type' => $parameter->type->value,
                'multiline' => false,
            ])
            ->values()
            ->all();
    }

    protected function formatHeaders(Request $request): array
    {
        return $request->headers
            ->map(fn($header) => [
                'name' => $header->key,
                'value' => $header->value,
            ])
            ->values()
            ->all();
    }

    protected function formatAuthentication(): array
    {
        if ( ! $this->authentication) {
            return ['type' => 'none'];
        }

        return [
            'type' => $this->authentication->getType(),
            'token' => '{{ token }}',
            'prefix' => $this->authentication->prefix(),
            'disabled' => false,
        ];
    }

    protected function formatBody(Request $request): array
    {
        $request = $request->generateDefaultBody();
        return $request->body?->forInsomnia();
    }

    private function validateResourceId(string $id, string $prefix): string
    {
        if (!str_starts_with($id, $prefix . '_')) {
            throw new InvalidArgumentException(
                sprintf('Invalid resource ID format. Expected prefix %s', $prefix)
            );
        }

        if (in_array($id, $this->resourceIds)) {
            throw new InvalidArgumentException(
                sprintf('Duplicate resource ID: %s', $id)
            );
        }

        return $id;
    }

    private function cleanUrl(string $url): string
    {
        $url = preg_replace('#/+#', '/', $url);
        $url = rtrim($url, '/');

        return preg_replace('/\{([^}]+)}/', ':$1', $url);
    }
}
