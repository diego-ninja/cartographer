<?php

namespace Ninja\Cartographer\Builders;

use Ninja\Cartographer\DTO\Request;
use Ninja\Cartographer\DTO\RequestGroup;
use Ninja\Cartographer\Exceptions\ExportException;
use Ramsey\Uuid\UuidInterface;

final class PostmanCollectionBuilder extends AbstractCollectionBuilder
{
    private ?array $protocolProfileBehavior = null;
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
            'auth' => $this->auth,
            'event' => $this->events,
        ]);
    }

    protected function processGroups(): array
    {
        if ($this->groups->isEmpty()) {
            return [];
        }

        $ungroupedRequests = $this->groups->filter(function ($item) {
            return $item instanceof Request;
        });

        $groups = $this->groups->filter(function ($item) {
            return $item instanceof RequestGroup;
        });

        $items = [];

        if ($ungroupedRequests->isNotEmpty()) {
            $items[] = [
                'name' => $this->config->get('cartographer.name', 'Cartographer Collection'),
                'item' => $ungroupedRequests->map->forPostman()->values()->all()
            ];
        }

        return array_merge(
            $items,
            $groups->map->forPostman()->values()->all()
        );
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

    public function setProtocolProfileBehavior(array $behavior): self
    {
        $this->protocolProfileBehavior = $behavior;
        return $this;
    }
}
