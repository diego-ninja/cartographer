<?php

namespace Ninja\Cartographer\Builders;

use Ninja\Cartographer\DTO\RequestGroup;
use Ninja\Cartographer\Exceptions\ExportException;
use Ramsey\Uuid\UuidInterface;

final class PostmanCollectionBuilder extends AbstractCollectionBuilder
{
    /**
     * @throws ExportException
     */
    public function build(): array
    {
        if (empty($this->structure['info'])) {
            throw ExportException::invalidStructure('Basic info not set');
        }

        return array_filter([
            'info' => $this->structure['info'],
            'variable' => $this->variables,
            'item' => $this->processGroups(),
        ]);
    }

    public function processGroups(): array
    {
        return $this->groups->map(fn(RequestGroup $group) => $group->forPostman())->values()->all();
    }

    protected function generateInfo(string $name, string $description, UuidInterface $id): array
    {
        return [
            '_postman_id' => $id->toString(),
            'name' => $name,
            'description' => $description,
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            'version' => [
                'major' => 1,
                'minor' => 0,
                'patch' => 0,
            ],
        ];
    }
}
