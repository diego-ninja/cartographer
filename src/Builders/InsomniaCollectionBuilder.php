<?php

namespace Ninja\Cartographer\Builders;

use Illuminate\Support\Str;
use Ninja\Cartographer\Exceptions\ExportException;
use Ninja\Cartographer\Exceptions\ValidationException;
use Ramsey\Uuid\UuidInterface;

final class InsomniaCollectionBuilder extends AbstractCollectionBuilder
{
    private string $workspaceId;
    private array $usedIds = [];

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

        return [
            '_type' => 'export',
            '__export_format' => 4,
            '__export_date' => date('Y-m-d H:i:s'),
            '__export_source' => 'cartographer',
            'resources' => array_merge(
                [
                    $this->createWorkspace(),
                    $this->createEnvironment(),
                ],
                $this->processGroups(),
            ),
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

    private function processGroups(): array
    {
        $resources = [];
        foreach ($this->groups->flatten() as $group) {
            $resources = array_merge($resources, $group->forInsomnia());
        }
        return $resources;
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
                [],
            ),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function generateResourceId(string $prefix): string
    {
        if ( ! in_array($prefix, ['wrk', 'env'])) {
            throw ValidationException::invalidResourceId($prefix, implode('/', ['wrk', 'env']));
        }

        $id = $prefix . '_' . Str::uuid()->toString();

        if (isset($this->usedIds[$id])) {
            throw ValidationException::duplicateResourceId($id);
        }

        $this->usedIds[$id] = true;
        return $id;
    }
}
