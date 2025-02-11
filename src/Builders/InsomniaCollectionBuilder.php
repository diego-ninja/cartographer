<?php

namespace Ninja\Cartographer\Builders;

use Ninja\Cartographer\DTO\Request;
use Illuminate\Support\Str;
use Ninja\Cartographer\Exceptions\ExportException;
use Ninja\Cartographer\Exceptions\ValidationException;
use Ramsey\Uuid\UuidInterface;

final class InsomniaCollectionBuilder extends AbstractCollectionBuilder
{
    private string $workspaceId;
    private array $resourceIds = [];

    /**
     * @throws ExportException
     * @throws ValidationException
     */
    public function build(): array
    {
        if (empty($this->structure['info'])) {
            throw ExportException::invalidStructure('Basic info not set');
        }

        $this->workspaceId = $this->generateResourceId('wrk');

        $resources = [
            $this->createWorkspace(),
            $this->createEnvironment()
        ];

        foreach ($this->events as $event) {
            $resources[] = $this->createScript($event);
        }

        return [
            '_type' => 'export',
            '__export_format' => 4,
            '__export_date' => date('Y-m-d H:i:s'),
            '__export_source' => 'cartographer',
            'resources' => array_merge($resources, $this->items),
        ];
    }

    protected function generateInfo(string $name, string $description, UuidInterface $id): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'id' => $id->toString(),
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function processStructuredRequests(): array
    {
        $resources = [];
        $this->processNestedGroups($this->requests->groupByNestedPath(), $resources);
        return $resources;
    }

    protected function processFlatRequests(): array
    {
        return $this->requests
            ->map(fn($request) => $this->formatRequest($request))
            ->values()
            ->all();
    }

    /**
     * @throws ValidationException
     */
    protected function formatRequest(Request $request): array
    {
        return [
            '_id' => $this->generateResourceId('req'),
            '_type' => 'request',
            'parentId' => $this->workspaceId,
            'modified' => time(),
            'created' => time(),
            'name' => $request->name,
            'description' => $request->description,
            'method' => $request->method->value,
            'url' => sprintf('{{ base_url }}/%s', $this->cleanUrl($request->uri)),
            'headers' => array_map(fn($header) => [
                'name' => $header->key,
                'value' => $header->value,
            ], $request->headers->all()),
            'parameters' => array_map(fn($param) => [
                'name' => $param->name,
                'value' => $param->value,
                'description' => $param->description,
                'disabled' => $param->disabled,
            ], $request->parameters->all()),
            'authentication' => $request->authentication ?? ['type' => 'none'],
            'body' => $request->body?->forInsomnia(),
        ];
    }

    private function createWorkspace(): array
    {
        return [
            '_id' => $this->workspaceId,
            '_type' => 'workspace',
            'parentId' => null,
            'name' => $this->structure['info']['name'],
            'description' => $this->structure['info']['description'],
            'scope' => 'collection',
        ];
    }

    /**
     * @throws ValidationException
     */
    private function createEnvironment(): array
    {
        return [
            '_id' => $this->generateResourceId('env'),
            '_type' => 'environment',
            'parentId' => $this->workspaceId,
            'name' => 'Base Environment',
            'data' => array_reduce(
                $this->variables,
                fn($carry, $variable) => array_merge($carry, [$variable['key'] => $variable['value']]),
                []
            ),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function processNestedGroups(array $groups, array &$resources, ?string $parentId = null): void
    {
        $parentId = $parentId ?? $this->workspaceId;
        $sortKey = 0;

        foreach ($groups as $segment => $data) {
            $folderId = $this->generateResourceId('fld');

            $resources[] = [
                '_id' => $folderId,
                '_type' => 'request_group',
                'parentId' => $parentId,
                'name' => Str::title($segment),
                'description' => sprintf('Endpoints for %s', $segment),
                'metaSortKey' => $sortKey,
            ];

            foreach ($data['requests'] as $request) {
                $resources[] = $this->formatRequest($request);
                $sortKey += 100;
            }

            if (!empty($data['children'])) {
                $this->processNestedGroups($data['children'], $resources, $folderId);
            }

            $sortKey += 1000;
        }
    }

    /**
     * @throws ValidationException
     */
    private function createScript(array $event): array
    {
        return [
            '_id' => $this->generateResourceId('scr'),
            '_type' => 'request_hook',
            'parentId' => $this->workspaceId,
            'modified' => time(),
            'created' => time(),
            'name' => ucfirst($event['type']) . ' Script',
            'description' => sprintf('Auto-generated %s script', $event['type']),
            'script' => $event['script'],
            'triggers' => [
                [
                    'name' => match($event['type']) {
                        'prerequest' => 'pre-request',
                        'test' => 'post-request',
                        default => $event['type']
                    }
                ]
            ]
        ];
    }

    /**
     * @throws ValidationException
     */
    private function generateResourceId(string $prefix): string
    {
        $id = $prefix . '_' . Str::uuid()->toString();

        if (isset($this->resourceIds[$id])) {
            throw ValidationException::duplicateResourceId($id);
        }

        $this->resourceIds[$id] = true;
        return $id;
    }

    private function cleanUrl(string $url): string
    {
        $url = preg_replace('#/+#', '/', $url);
        $url = rtrim($url, '/');
        return preg_replace('/\{([^}]+)}/', ':$1', $url);
    }
}
