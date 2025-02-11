<?php

namespace Ninja\Cartographer\Builders;

use Ninja\Cartographer\DTO\Request;
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
            'event' => $this->formatEvents(),
            'auth' => $this->auth,
            'item' => $this->items,
        ]);
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

    protected function formatEvents(): array
    {
        return array_map(function (array $event) {
            return [
                'listen' => $event['type'],
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => explode("\n", $event['script']),
                ],
            ];
        }, $this->events);
    }

    protected function processStructuredRequests(): array
    {
        return $this->processNestedGroups($this->requests->groupByNestedPath());
    }

    protected function processFlatRequests(): array
    {
        return $this->requests
            ->map(fn($request) => $this->formatRequest($request))
            ->values()
            ->all();
    }

    protected function formatRequest(Request $request): array
    {
        $formattedRequest = [
            '_postman_id' => $request->id->toString(),
            'name' => $request->name,
            'request' => [
                'method' => $request->method->value,
                'header' => $request->headers->formatted(),
                'url' => $request->url->array(),
                'description' => $request->description,
            ],
            'response' => [],
        ];

        if ($request->body) {
            $formattedRequest['request']['body'] = $request->body->forPostman();
        }

        if ($request->authentication) {
            $formattedRequest['request']['auth'] = $request->authentication;
        }

        return $formattedRequest;
    }

    private function processNestedGroups(array $groups): array
    {
        $items = [];

        foreach ($groups as $segment => $data) {
            $folder = [
                'name' => $segment,
                'description' => sprintf('Endpoints for %s', $segment),
                'item' => [],
            ];

            foreach ($data['requests'] as $request) {
                $folder['item'][] = $this->formatRequest($request);
            }

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
}
